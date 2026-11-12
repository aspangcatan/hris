<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReorientationService
{
    public function modules(): array
    {
        return config('reorientation.modules');
    }

    public function isValidModule(int $moduleNo): bool
    {
        return array_key_exists($moduleNo, $this->modules());
    }

    public function pdfPath(int $moduleNo): string
    {
        return resource_path($this->modules()[$moduleNo]['pdf']);
    }

    // Modules are taken in order: a module opens once the one before it has been
    // submitted at least once (any status other than not started).
    public function isModuleUnlocked(int $userId, int $moduleNo): bool
    {
        $previous = $this->previousModule($moduleNo);
        return $previous === null || (bool) $this->getSubmission($userId, $previous);
    }

    private function previousModule(int $moduleNo): ?int
    {
        $previous = null;
        foreach (array_keys($this->modules()) as $no) {
            if ($no === $moduleNo) {
                return $previous;
            }
            $previous = $no;
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Questions
    // -----------------------------------------------------------------------

    public function getActiveQuestions(int $moduleNo)
    {
        return DB::table('hris.reorientation_questions')
            ->where('module_no', $moduleNo)
            ->where('is_active', 1)
            ->orderBy('order_no')
            ->orderBy('id')
            ->get(['id', 'module_no', 'question', 'order_no']);
    }

    public function saveQuestion(int $moduleNo, string $question, ?int $id = null): void
    {
        if ($id) {
            $updated = DB::table('hris.reorientation_questions')
                ->where('id', $id)
                ->where('module_no', $moduleNo)
                ->where('is_active', 1)
                ->update(['question' => $question, 'updated_at' => now()]);

            if (!$updated) {
                throw new \Exception('Question not found.');
            }
            return;
        }

        $nextOrder = (int) DB::table('hris.reorientation_questions')
            ->where('module_no', $moduleNo)
            ->where('is_active', 1)
            ->max('order_no') + 1;

        DB::table('hris.reorientation_questions')->insert([
            'module_no'  => $moduleNo,
            'question'   => $question,
            'order_no'   => $nextOrder,
            'is_active'  => 1,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Soft delete: submitted answers keep their own question_text snapshot
    public function deleteQuestion(int $id): void
    {
        DB::table('hris.reorientation_questions')
            ->where('id', $id)
            ->update(['is_active' => 0, 'updated_at' => now()]);
    }

    public function moveQuestion(int $id, string $direction): void
    {
        $question = DB::table('hris.reorientation_questions')->where('id', $id)->where('is_active', 1)->first();
        if (!$question) {
            throw new \Exception('Question not found.');
        }

        $ordered = $this->getActiveQuestions($question->module_no)->pluck('id')->values()->all();
        $index   = array_search($id, $ordered);
        $swap    = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swap < 0 || $swap >= count($ordered)) {
            return;
        }

        [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $order => $qid) {
                DB::table('hris.reorientation_questions')->where('id', $qid)->update(['order_no' => $order + 1]);
            }
        });
    }

    // -----------------------------------------------------------------------
    // Employee post test
    // -----------------------------------------------------------------------

    public function getSubmission(int $userId, int $moduleNo)
    {
        return DB::table('hris.reorientation_submissions')
            ->where('user_id', $userId)
            ->where('module_no', $moduleNo)
            ->first();
    }

    public function getAnswers(int $submissionId)
    {
        return DB::table('hris.reorientation_answers')
            ->where('submission_id', $submissionId)
            ->orderBy('id')
            ->get(['question_id', 'question_text', 'answer'])
            ->keyBy('question_id');
    }

    // Module list with each module's status for one employee
    public function getModuleSummaries(int $userId): array
    {
        $submissions = DB::table('hris.reorientation_submissions')
            ->where('user_id', $userId)
            ->get()
            ->keyBy('module_no');

        $checkerIds = $submissions->pluck('checked_by')->filter()->unique()->values();
        $checkers   = DB::table('tdh_user.users')
            ->whereIn('id', $checkerIds)
            ->get(['id', 'fname', 'lname'])
            ->keyBy('id');

        $result = [];
        $previousSubmitted = true;
        foreach ($this->modules() as $no => $module) {
            $sub = $submissions->get($no);
            $checker = $sub && $sub->checked_by ? $checkers->get($sub->checked_by) : null;

            $result[] = [
                'module_no'     => $no,
                'title'         => $module['title'],
                'read_url'      => route('reorientation.read', $no),
                'locked'        => !$previousSubmitted,
                'status'        => $sub->status ?? 'not_started',
                'attempt_no'    => $sub->attempt_no ?? 0,
                'remarks'       => $sub->remarks ?? null,
                'submitted_at'  => $sub->submitted_at ?? null,
                'checked_at'    => $sub->checked_at ?? null,
                'checked_by'    => $checker ? trim($checker->fname . ' ' . $checker->lname) : null,
                'submission_id' => $sub->id ?? null,
            ];
            $previousSubmitted = (bool) $sub;
        }

        return $result;
    }

    // Questions merged with the employee's answers. For a locked submission
    // (submitted/passed) the answered snapshot is shown, not the current questions.
    public function getPostTest(int $userId, int $moduleNo): array
    {
        $submission = $this->getSubmission($userId, $moduleNo);
        $answers    = $submission ? $this->getAnswers($submission->id) : collect();
        $locked     = $submission && in_array($submission->status, ['submitted', 'passed'], true);

        if ($locked) {
            $items = $answers->values()->map(fn($a) => [
                'question_id' => $a->question_id,
                'question'    => $a->question_text,
                'answer'      => $a->answer,
            ]);
        } else {
            $items = $this->getActiveQuestions($moduleNo)->map(fn($q) => [
                'question_id' => $q->id,
                'question'    => $q->question,
                'answer'      => optional($answers->get($q->id))->answer ?? '',
            ]);
        }

        return [
            'module_no'  => $moduleNo,
            'status'     => $submission->status ?? 'not_started',
            'attempt_no' => $submission->attempt_no ?? 0,
            'remarks'    => $submission->remarks ?? null,
            'locked'     => $locked,
            'items'      => $items->values(),
        ];
    }

    public function submitPostTest(int $userId, int $moduleNo, array $answers): void
    {
        if (!$this->isModuleUnlocked($userId, $moduleNo)) {
            throw new \Exception('Please submit the previous module\'s post test first.');
        }

        $submission = $this->getSubmission($userId, $moduleNo);

        if ($submission && in_array($submission->status, ['submitted', 'passed'], true)) {
            throw new \Exception($submission->status === 'passed'
                ? 'This post test has already been passed.'
                : 'This post test is already submitted and waiting to be checked.');
        }

        $questions = $this->getActiveQuestions($moduleNo);
        if ($questions->isEmpty()) {
            throw new \Exception('This module has no post test questions yet.');
        }

        foreach ($questions as $q) {
            if (trim((string) ($answers[$q->id] ?? '')) === '') {
                throw new \Exception('Please answer all questions before submitting.');
            }
        }

        DB::transaction(function () use ($userId, $moduleNo, $answers, $questions, $submission) {
            if ($submission) {
                $submissionId = $submission->id;
                DB::table('hris.reorientation_submissions')->where('id', $submissionId)->update([
                    'status'       => 'submitted',
                    'attempt_no'   => $submission->attempt_no + 1,
                    'checked_by'   => null,
                    'checked_at'   => null,
                    'submitted_at' => now(),
                    'updated_at'   => now(),
                ]);
                DB::table('hris.reorientation_answers')->where('submission_id', $submissionId)->delete();
            } else {
                $submissionId = DB::table('hris.reorientation_submissions')->insertGetId([
                    'user_id'      => $userId,
                    'module_no'    => $moduleNo,
                    'status'       => 'submitted',
                    'attempt_no'   => 1,
                    'submitted_at' => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $rows = $questions->map(fn($q) => [
                'submission_id' => $submissionId,
                'question_id'   => $q->id,
                'question_text' => $q->question,
                'answer'        => trim((string) $answers[$q->id]),
                'created_at'    => now(),
                'updated_at'    => now(),
            ])->all();

            DB::table('hris.reorientation_answers')->insert($rows);
        });
    }

    // -----------------------------------------------------------------------
    // Checking (HR)
    // -----------------------------------------------------------------------

    public function getSubmissionForChecking(int $userId, int $moduleNo): ?array
    {
        $submission = $this->getSubmission($userId, $moduleNo);
        if (!$submission) {
            return null;
        }

        return [
            'submission_id' => $submission->id,
            'module_no'     => $moduleNo,
            'title'         => $this->modules()[$moduleNo]['title'],
            'status'        => $submission->status,
            'attempt_no'    => $submission->attempt_no,
            'remarks'       => $submission->remarks,
            'submitted_at'  => $submission->submitted_at,
            'items'         => $this->getAnswers($submission->id)->values()->map(fn($a) => [
                'question' => $a->question_text,
                'answer'   => $a->answer,
            ]),
        ];
    }

    public function checkSubmission(int $submissionId, string $status, ?string $remarks): void
    {
        $updated = DB::table('hris.reorientation_submissions')
            ->where('id', $submissionId)
            ->update([
                'status'     => $status,
                'remarks'    => $remarks,
                'checked_by' => Auth::id(),
                'checked_at' => now(),
                'updated_at' => now(),
            ]);

        if (!$updated) {
            throw new \Exception('Submission not found.');
        }
    }

    // -----------------------------------------------------------------------
    // Reorientation Answers page (HR)
    // -----------------------------------------------------------------------

    // Active employees (tdh_user.users status 1 or JO) in hris.employees
    private function activeEmployeesQuery()
    {
        return DB::table('hris.employees as e')
            ->join('tdh_user.users as u', 'e.user_id', '=', 'u.id')
            ->whereIn('u.status', ['1', 'JO']);
    }

    // Employees for one module filtered by status. "not_started" lists active
    // employees with no submission for that module yet.
    public function getAnswerList(int $moduleNo, string $status, string $search = '', int $perPage = 25)
    {
        $query = DB::table('hris.employees as e')
            ->join('tdh_user.users as u', 'e.user_id', '=', 'u.id')
            ->leftJoin('tdh_user.section as s', 'u.section', '=', 's.id')
            ->leftJoin('hris.reorientation_submissions as rs', function ($join) use ($moduleNo) {
                $join->on('rs.user_id', '=', 'e.user_id')->where('rs.module_no', '=', $moduleNo);
            })
            ->leftJoin('tdh_user.users as c', 'rs.checked_by', '=', 'c.id');

        if ($status === 'not_started') {
            $query->whereNull('rs.id')->whereIn('u.status', ['1', 'JO']);
        } else {
            $query->where('rs.status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.fname', 'LIKE', "%{$search}%")
                  ->orWhere('u.lname', 'LIKE', "%{$search}%")
                  ->orWhereRaw("CONCAT(u.fname, ' ', u.lname) LIKE ?", ["%{$search}%"]);
            });
        }

        // Pending: oldest submission first so HR checks in the order received
        if ($status === 'submitted') {
            $query->orderBy('rs.submitted_at');
        }
        $query->orderBy('u.lname')->orderBy('u.fname');

        return $query->paginate($perPage, [
            'e.user_id', 'u.fname', 'u.mname', 'u.lname', 'u.suffix', 's.description as section',
            'rs.id as submission_id', 'rs.status', 'rs.attempt_no', 'rs.remarks',
            'rs.submitted_at', 'rs.checked_at', 'c.fname as checker_fname', 'c.lname as checker_lname',
        ]);
    }

    // Per-module count of each status, for the tab and filter badges
    public function getAnswerCounts(): array
    {
        $rows = DB::table('hris.reorientation_submissions')
            ->select('module_no', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('module_no', 'status')
            ->get();

        $submittedActive = $this->activeEmployeesQuery()
            ->join('hris.reorientation_submissions as rs', 'rs.user_id', '=', 'e.user_id')
            ->select('rs.module_no', DB::raw('COUNT(*) as total'))
            ->groupBy('rs.module_no')
            ->pluck('total', 'module_no');

        $activeTotal = $this->activeEmployeesQuery()->count();

        $counts = [];
        foreach (array_keys($this->modules()) as $no) {
            $byStatus = $rows->where('module_no', $no)->pluck('total', 'status');
            $counts[$no] = [
                'not_started' => $activeTotal - (int) ($submittedActive[$no] ?? 0),
                'submitted'   => (int) ($byStatus['submitted'] ?? 0),
                'passed'      => (int) ($byStatus['passed'] ?? 0),
                'failed'      => (int) ($byStatus['failed'] ?? 0),
            ];
        }

        return $counts;
    }

    // Marks several submissions of one module at once. Returns how many were updated.
    public function checkSubmissions(int $moduleNo, array $submissionIds, string $status, ?string $remarks): int
    {
        $ids = array_values(array_unique(array_map('intval', $submissionIds)));
        if (!$ids) {
            throw new \Exception('Please select at least one employee.');
        }

        return DB::table('hris.reorientation_submissions')
            ->whereIn('id', $ids)
            ->where('module_no', $moduleNo)
            ->update([
                'status'     => $status,
                'remarks'    => $remarks,
                'checked_by' => Auth::id(),
                'checked_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
