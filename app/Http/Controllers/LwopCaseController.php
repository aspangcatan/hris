<?php

namespace App\Http\Controllers;

use App\Services\LwopCaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * LWOP Settings: the weekend cases. Saturday and Sunday are OFF; each case
 * says, for the status of the Friday before and the Monday after, whether
 * that Saturday and Sunday count as LWOP. A combination with no case = the
 * weekend is present. Holidays are always present. Cases change LWOP days
 * and earned credits only, never pay; a cutoff keeps its stored figures until
 * it is regenerated.
 */
class LwopCaseController extends Controller
{
    public function index()
    {
        return view('settings.lwop_cases');
    }

    public function list()
    {
        $rows = DB::table('lwop_weekend_cases as c')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', DB::raw('COALESCE(c.updated_by, c.created_by)'))
            ->orderBy('c.sort_order')->orderBy('c.id')
            ->get([
                'c.id', 'c.friday_status', 'c.monday_status', 'c.saturday_lwop', 'c.sunday_lwop', 'c.remarks', 'c.updated_at',
                DB::raw("TRIM(CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(u.lname, ''))) as changed_by_name"),
            ]);

        return response()->json([
            'data' => $rows->map(fn($r) => [
                'id' => (int) $r->id,
                'friday' => $r->friday_status,
                'monday' => $r->monday_status,
                'saturday' => (bool) $r->saturday_lwop,
                'sunday' => (bool) $r->sunday_lwop,
                'remarks' => $r->remarks,
                'changed_by' => $r->changed_by_name ?: null,
                'updated_at' => $r->updated_at,
            ])->values(),
        ]);
    }

    public function save(Request $request)
    {
        $statuses = implode(',', LwopCaseService::STATUSES);
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'friday' => 'required|in:' . $statuses,
            'monday' => 'required|in:' . $statuses,
            'saturday' => 'required|boolean',
            'sunday' => 'required|boolean',
            'remarks' => 'nullable|string|max:255',
        ]);
        $id = (int) ($validated['id'] ?? 0);

        $duplicate = DB::table('lwop_weekend_cases')
            ->where('friday_status', $validated['friday'])
            ->where('monday_status', $validated['monday'])
            ->when($id > 0, fn($q) => $q->where('id', '<>', $id))
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'A case for this Friday and Monday already exists. Edit that case instead.'], 422);
        }

        $values = [
            'friday_status' => $validated['friday'],
            'monday_status' => $validated['monday'],
            'saturday_lwop' => (bool) $validated['saturday'],
            'sunday_lwop' => (bool) $validated['sunday'],
            'remarks' => $validated['remarks'] ?? null,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ];

        if ($id > 0) {
            if (!DB::table('lwop_weekend_cases')->where('id', $id)->exists()) {
                return response()->json(['message' => 'Case not found.'], 404);
            }
            DB::table('lwop_weekend_cases')->where('id', $id)->update($values);
            $message = 'Case updated.';
        } else {
            $values['sort_order'] = (int) DB::table('lwop_weekend_cases')->max('sort_order') + 1;
            DB::table('lwop_weekend_cases')->insert($values + ['created_by' => Auth::id(), 'created_at' => now()]);
            $message = 'Case added.';
        }
        LwopCaseService::flush();

        return response()->json(['message' => $message . ' Cutoffs already generated keep their figures until regenerated.']);
    }

    public function delete(Request $request)
    {
        $deleted = DB::table('lwop_weekend_cases')->where('id', (int) $request->input('id'))->delete();
        LwopCaseService::flush();

        return $deleted
            ? response()->json(['message' => 'Case deleted.'])
            : response()->json(['message' => 'Case not found.'], 404);
    }
}
