<?php

namespace App\Http\Controllers;

use App\Services\LeaveOpeningBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Opening Leave Balances page: certify starting balances and check the ledger. */
class LeaveOpeningBalanceController extends Controller
{
    private LeaveOpeningBalanceService $openings;

    public function __construct(LeaveOpeningBalanceService $openings)
    {
        $this->openings = $openings;
    }

    public function page()
    {
        return view('settings.leave_opening_balances');
    }

    public function preview(Request $request)
    {
        try {
            $validated = $request->validate(['as_of' => 'required|date|before_or_equal:today']);

            return response()->json(['success' => true, 'data' => $this->openings->preview($validated['as_of'])]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function certify(Request $request)
    {
        try {
            $validated = $request->validate([
                'as_of' => 'required|date|before_or_equal:today',
                'employees' => 'required|array|min:1',
                'employees.*' => 'integer',
            ]);
            $result = $this->openings->certify($validated['as_of'], array_map('intval', $validated['employees']), (int) Auth::id());

            if ($result['certified'] === 0) {
                return response()->json(['success' => false, 'message' => 'Nothing was certified: the selected employees are already certified or blocked.'], 422);
            }

            $message = "Opening balances certified for {$result['certified']} employee(s).";
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} already certified or blocked were skipped.";
            }

            return response()->json(['success' => true, 'message' => $message] + $result);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function reconcile()
    {
        try {
            return response()->json(['success' => true] + $this->openings->reconcile());
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function validationError(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
    }
}
