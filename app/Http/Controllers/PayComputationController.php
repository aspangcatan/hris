<?php

namespace App\Http\Controllers;

use App\Services\DTRServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Pay Computations settings: the named rate formulas, and which shift uses which. */
class PayComputationController extends Controller
{
    private DTRServices $dtrServices;

    public function __construct(DTRServices $dtrServices)
    {
        $this->dtrServices = $dtrServices;
    }

    public function page()
    {
        return view('settings.pay_computations');
    }

    public function index()
    {
        try {
            $usage = DB::table('hris.schedule_master_lists')
                ->whereNotNull('pay_computation_id')
                ->groupBy('pay_computation_id')
                ->selectRaw('pay_computation_id, COUNT(*) AS shifts')
                ->pluck('shifts', 'pay_computation_id');

            $computations = DB::table('hris.pay_computations')->orderByDesc('is_default')->orderBy('name')->get()
                ->map(function ($computation) use ($usage) {
                    $computation->shifts = (int) ($usage[$computation->id] ?? 0);

                    return $computation;
                });

            $shifts = DB::table('hris.schedule_master_lists')->orderBy('code')
                ->get(['id', 'code', 'label', 'time_in', 'time_out', 'break_out', 'break_in', 'pay_computation_id'])
                ->reject(fn($shift) => strtoupper(trim((string) $shift->code)) === 'OFF')
                ->map(fn($shift) => [
                    'id' => (int) $shift->id,
                    'code' => $shift->code,
                    'label' => $shift->label,
                    'hours' => round($this->dtrServices->scheduledWorkMinutes($shift) / 60, 2),
                    'pay_computation_id' => $shift->pay_computation_id !== null ? (int) $shift->pay_computation_id : null,
                ])
                ->values();

            return response()->json(['success' => true, 'computations' => $computations, 'shifts' => $shifts]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function save(Request $request)
    {
        try {
            $id = $request->input('id') ? (int) $request->input('id') : null;
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100', Rule::unique('pay_computations', 'name')->ignore($id)],
                'working_days_per_month' => 'required|numeric|min:1|max:31',
                'standard_hours_per_day' => 'required|numeric|min:0.5|max:24',
                'is_default' => 'boolean',
                'remarks' => 'nullable|string|max:1000',
            ], ['name.unique' => 'Another computation already has this name.']);

            DB::transaction(function () use ($validated, $id) {
                $current = $id ? DB::table('hris.pay_computations')->where('id', $id)->lockForUpdate()->first() : null;
                if ($id && !$current) {
                    throw ValidationException::withMessages(['id' => 'That computation no longer exists.']);
                }
                $makeDefault = !empty($validated['is_default']);
                if ($current && $current->is_default && !$makeDefault) {
                    throw ValidationException::withMessages(['is_default' => 'One computation has to be the default. Make another one the default instead.']);
                }
                if ($makeDefault) {
                    DB::table('hris.pay_computations')->where('is_default', 1)->update(['is_default' => 0]);
                }

                $values = [
                    'name' => trim($validated['name']),
                    'working_days_per_month' => $validated['working_days_per_month'],
                    'standard_hours_per_day' => $validated['standard_hours_per_day'],
                    'is_default' => $makeDefault ? 1 : 0,
                    'remarks' => $validated['remarks'] ?? null,
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ];
                if ($id) {
                    DB::table('hris.pay_computations')->where('id', $id)->update($values);
                } else {
                    DB::table('hris.pay_computations')->insert($values + ['created_at' => now()]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Computation saved. It applies to cutoffs generated from now on.']);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $computation = DB::table('hris.pay_computations')->where('id', (int) $id)->first();
            if (!$computation) {
                return response()->json(['success' => false, 'message' => 'That computation no longer exists.'], 404);
            }
            if ($computation->is_default) {
                return response()->json(['success' => false, 'message' => 'The default computation cannot be deleted.'], 422);
            }
            if (DB::table('hris.schedule_master_lists')->where('pay_computation_id', $computation->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Shifts still use this computation. Move them to another one first.'], 422);
            }
            DB::table('hris.pay_computations')->where('id', $computation->id)->delete();

            return response()->json(['success' => true, 'message' => 'Computation deleted.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function assignShift(Request $request)
    {
        try {
            $validated = $request->validate([
                'shift_id' => 'required|integer|exists:schedule_master_lists,id',
                'pay_computation_id' => 'nullable|integer|exists:pay_computations,id',
            ]);
            DB::table('hris.schedule_master_lists')->where('id', $validated['shift_id'])
                ->update(['pay_computation_id' => $validated['pay_computation_id'] ?? null, 'updated_at' => now()]);

            return response()->json(['success' => true, 'message' => 'Shift updated. It applies to cutoffs generated from now on.']);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function validationError(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
    }
}
