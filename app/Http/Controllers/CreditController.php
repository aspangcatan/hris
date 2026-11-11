<?php

namespace App\Http\Controllers;

use App\Services\EarnedCreditBatchService;
use App\Services\LeaveCreditService;
use App\Services\PayrollServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditController extends Controller
{
    protected $leaveCreditService, $payrollServices;
    private EarnedCreditBatchService $batches;

    public function __construct(LeaveCreditService $leaveCreditService, PayrollServices $payrollServices, EarnedCreditBatchService $batches)
    {
        $this->batches = $batches;
        $this->leaveCreditService = $leaveCreditService;
        $this->payrollServices = $payrollServices;
    }

    public function getCreditEarned(Request $request)
    {
        try {
            $logs = DB::table('hris.credit_earned_logs as l')
                ->join('tdh_user.users as u', 'u.id', '=', 'l.user_id')
                ->join('hris.leave_types as t', 't.id', '=', 'l.leave_type_id')
                ->where('l.year', $request->year)
                ->where('l.month', $request->month)
                ->where('u.division', $request->division)
                ->where('u.section', $request->section)
                ->get(['u.fname', 'u.mname', 'u.lname', 'u.suffix', 'l.month', 'l.year', 't.description', 'l.total_present', 'l.total_lateundertime_minutes', 'l.earned_points', 'l.deducted_points']);
            return response()->json($logs);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    /** Leave Credits Earned page (sidebar). */
    public function leaveCreditsEarnedPage()
    {
        return view('settings.leave_credits_earned');
    }


    /** Months with finalized cutoffs and their batch counts (newest first). */
    public function leaveCreditMonths()
    {
        try {
            return response()->json(['success' => true, 'data' => $this->batches->months()]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /** The batch for one month: every employee in its finalized cutoffs. */
    public function previewMonthLeaveCredits(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => 'required|integer|min:2000|max:2100',
                'month' => 'required|integer|min:1|max:12',
            ]);
            $batch = $this->batches->compute((int) $validated['year'], (int) $validated['month']);

            return response()->json([
                'success' => true,
                'period_label' => $batch['period_label'],
                'cutoffs' => $batch['cutoffs'],
                'draft_cutoffs' => $batch['draft_cutoffs'],
                'data' => $batch['employees'],
                'ready' => $batch['employees']->where('status', 'ready')->count(),
                'unfiled_absences' => $this->unfiledAbsences($batch),
                'message' => $this->batchNotice($batch),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /** One-click Post: credits the selected employees whose rows are ready. */
    public function postMonthLeaveCredits(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => 'required|integer|min:2000|max:2100',
                'month' => 'required|integer|min:1|max:12',
                'employees' => 'required|array|min:1',
                'employees.*' => 'integer',
            ]);

            $result = $this->batches->post(
                (int) $validated['year'],
                (int) $validated['month'],
                array_map('intval', $validated['employees']),
                Auth::id() ? (int) Auth::id() : null
            );
            $label = $result['batch']['period_label'];

            if (!empty($result['refused'])) {
                $unfiled = $this->unfiledAbsences($result['batch']);
                $names = collect($unfiled)->map(fn($u) => "{$u['name']} ({$u['detail']})")->implode('; ');

                return response()->json([
                    'success' => false,
                    'message' => "Nothing was posted for {$label}. Resolve these unfiled absences or untick the employees, then post again: {$names}.",
                    'unfiled_absences' => $unfiled,
                ], 422);
            }

            if ($result['credited'] === 0) {
                $message = $this->batchNotice($result['batch'])
                    ?? "Nothing was posted for {$label}: the selected employees are already credited or blocked.";

                return response()->json(['success' => false, 'message' => $message], 422);
            }

            $message = "Leave credits earned for {$label} were posted to {$result['credited']} "
                . ($result['credited'] === 1 ? 'employee' : 'employees') . '.';
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} already credited " . ($result['skipped'] === 1 ? 'was' : 'were') . ' skipped.';
            }
            if ($result['blocked'] > 0) {
                $message .= " {$result['blocked']} blocked " . ($result['blocked'] === 1 ? 'was' : 'were') . ' not posted.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'credited' => $result['credited'],
                'skipped' => $result['skipped'],
                'blocked' => $result['blocked'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /** Employees with unfiled absences: [['name' =>, 'detail' =>], ...]. */
    private function unfiledAbsences(array $batch): array
    {
        return $batch['employees']
            ->filter(fn($e) => $e['status'] === 'unfiled')
            ->map(fn($e) => ['name' => $e['name'], 'detail' => $e['unfiled_absences']])
            ->values()
            ->all();
    }

    /** Why the batch has nothing to post, or null if at least one row is ready. */
    private function batchNotice(array $batch): ?string
    {
        $label = $batch['period_label'];
        $employees = $batch['employees'];

        if (empty($batch['cutoffs'])) {
            return empty($batch['draft_cutoffs'])
                ? "There are no cutoffs for {$label} yet."
                : "The cutoffs for {$label} are still drafts. Finalize them on the Cutoff Summary page first.";
        }
        if ($employees->isEmpty()) {
            return "There are no employees to credit in the finalized cutoffs for {$label}.";
        }
        if ($employees->where('status', 'ready')->isNotEmpty()) {
            return null;
        }
        if ($employees->where('status', 'credited')->count() === $employees->count()) {
            return "All employees were already credited for {$label}. Nothing to post.";
        }
        if ($employees->where('status', 'unfiled')->isNotEmpty()) {
            return null; // named in the unfiled absences notice
        }
        if ($employees->where('status', 'blocked')->every(fn($e) => strpos((string) $e['reason'], 'No attendance') === 0)) {
            return "None of the employees still to be credited for {$label} has any attendance in the finalized cutoffs. "
                . 'Check that the time logs are complete, then reopen and regenerate the cutoff.';
        }

        return "Nothing is ready to post for {$label}. See the reason on each blocked row.";
    }
}
