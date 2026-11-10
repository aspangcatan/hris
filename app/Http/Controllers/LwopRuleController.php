<?php

namespace App\Http\Controllers;

use App\Services\LwopRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * LWOP Settings: effective-dated rule sets for LWOP identification (CSC
 * MC 41 s. 1998, Rule XVI, Sec. 33) and earned credits. A cutoff uses the
 * rule set in force on its period start. A rule set already used by a
 * generated cutoff is locked (add a newer one instead); the earliest (base)
 * rule set can never be deleted and keeps its effective date.
 */
class LwopRuleController extends Controller
{
    private const BOOLEAN_FIELDS = [
        'count_rest_days_in_long_runs',
        'holidays_bridge_runs',
        'partial_day_breaks_run',
        'absences_count_without_pay',
        'absences_join_runs',
    ];

    public function index()
    {
        return view('settings.lwop_rules');
    }

    public function list()
    {
        $hasUsage = $this->cutoffsTrackRuleSets();
        $baseId = DB::table('lwop_rule_sets')->orderBy('effective_from')->value('id');

        $rows = DB::table('lwop_rule_sets as r')
            ->leftJoin('tdh_user.users as c', 'c.id', '=', 'r.created_by')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'r.updated_by')
            ->orderByDesc('r.effective_from')
            ->get([
                'r.*',
                DB::raw("TRIM(CONCAT(COALESCE(c.fname, ''), ' ', COALESCE(c.lname, ''))) as created_by_name"),
                DB::raw("TRIM(CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(u.lname, ''))) as updated_by_name"),
            ]);

        $usage = $hasUsage
            ? DB::table('cutoff_summaries')->whereNotNull('lwop_rule_set_id')
                ->groupBy('lwop_rule_set_id')->pluck(DB::raw('COUNT(*)'), 'lwop_rule_set_id')
            : collect();

        $today = now()->toDateString();
        $currentId = DB::table('lwop_rule_sets')->where('effective_from', '<=', $today)
            ->orderByDesc('effective_from')->value('id');

        return response()->json([
            'data' => $rows->map(function ($row) use ($usage, $baseId, $currentId) {
                $rules = LwopRuleService::normalize($row);
                $used = (int) ($usage[$row->id] ?? 0);

                return array_merge((array) $rules, [
                    'legal_basis' => $row->legal_basis,
                    'remarks' => $row->remarks,
                    'created_by_name' => $row->created_by_name ?: null,
                    'updated_by_name' => $row->updated_by_name ?: null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'cutoffs_using' => $used,
                    'is_base' => (int) $row->id === (int) $baseId,
                    'is_current' => (int) $row->id === (int) $currentId,
                    'locked' => $used > 0,
                ]);
            })->values(),
        ]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'effective_from' => 'required|date',
            'legal_basis' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:2000',
            'long_run_min_days' => 'required|integer|min:0|max:366',
            'rest_day_source' => 'required|in:' . implode(',', LwopRuleService::REST_DAY_SOURCES),
            'partial_day_mode' => 'required|in:' . implode(',', LwopRuleService::PARTIAL_DAY_MODES),
            'month_basis_days' => 'required|integer|min:1|max:31',
            'fractional_key_mode' => 'required|in:' . implode(',', LwopRuleService::FRACTIONAL_KEY_MODES),
            'rest_day_rule' => 'sometimes|in:' . implode(',', LwopRuleService::REST_DAY_RULES),
            'after_lwop_includes_holidays' => 'sometimes|boolean',
        ] + array_fill_keys(self::BOOLEAN_FIELDS, 'required|boolean'));

        $id = (int) ($validated['id'] ?? 0);
        $effectiveFrom = date('Y-m-d', strtotime($validated['effective_from']));

        $values = [
            'effective_from' => $effectiveFrom,
            'legal_basis' => $validated['legal_basis'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'long_run_min_days' => (int) $validated['long_run_min_days'],
            'rest_day_source' => $validated['rest_day_source'],
            'partial_day_mode' => $validated['partial_day_mode'],
            'month_basis_days' => (int) $validated['month_basis_days'],
            'fractional_key_mode' => $validated['fractional_key_mode'],
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ];
        // Agency weekend rule (migration 2026_11_10_300000): saved once its columns exist.
        if ($this->ruleSetsHaveAfterLwopColumns()) {
            $values['rest_day_rule'] = $validated['rest_day_rule'] ?? 'long_runs';
            $values['after_lwop_includes_holidays'] = (bool) ($validated['after_lwop_includes_holidays'] ?? true);
        }
        foreach (self::BOOLEAN_FIELDS as $field) {
            $values[$field] = (bool) $validated[$field];
        }

        $duplicate = DB::table('lwop_rule_sets')->where('effective_from', $effectiveFrom)
            ->when($id > 0, fn($q) => $q->where('id', '<>', $id))->exists();
        if ($duplicate) {
            return response()->json(['message' => "Another rule set already takes effect on {$effectiveFrom}."], 422);
        }

        if ($id > 0) {
            $existing = DB::table('lwop_rule_sets')->where('id', $id)->first();
            if (!$existing) {
                return response()->json(['message' => 'Rule set not found.'], 404);
            }
            if ($this->cutoffsUsing($id) > 0) {
                return response()->json(['message' => 'This rule set was already used by generated cutoffs, so it can no longer be changed. Add a new rule set with a later effective date instead.'], 422);
            }
            $baseId = DB::table('lwop_rule_sets')->orderBy('effective_from')->value('id');
            if ((int) $baseId === $id && $effectiveFrom !== (string) $existing->effective_from) {
                return response()->json(['message' => 'The base rule set keeps its effective date (it covers every date before the newer rule sets).'], 422);
            }

            DB::table('lwop_rule_sets')->where('id', $id)->update($values);
            LwopRuleService::flush();

            return response()->json(['message' => 'Rule set updated.' . $this->regenerateNote($effectiveFrom)]);
        }

        $earliest = DB::table('lwop_rule_sets')->min('effective_from');
        if ($earliest !== null && $effectiveFrom < $earliest) {
            return response()->json(['message' => "The effective date must be on or after the base rule set ({$earliest})."], 422);
        }

        DB::table('lwop_rule_sets')->insert($values + ['created_by' => Auth::id(), 'created_at' => now()]);
        LwopRuleService::flush();

        return response()->json(['message' => 'Rule set added.' . $this->regenerateNote($effectiveFrom)]);
    }

    public function delete(Request $request)
    {
        $id = (int) $request->input('id');
        $row = DB::table('lwop_rule_sets')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Rule set not found.'], 404);
        }

        $baseId = DB::table('lwop_rule_sets')->orderBy('effective_from')->value('id');
        if ((int) $baseId === $id) {
            return response()->json(['message' => 'The base rule set cannot be deleted.'], 422);
        }
        if ($this->cutoffsUsing($id) > 0) {
            return response()->json(['message' => 'This rule set was already used by generated cutoffs, so it cannot be deleted.'], 422);
        }

        DB::table('lwop_rule_sets')->where('id', $id)->delete();
        LwopRuleService::flush();

        return response()->json(['message' => 'Rule set deleted.']);
    }

    private function cutoffsUsing(int $id): int
    {
        return $this->cutoffsTrackRuleSets()
            ? DB::table('cutoff_summaries')->where('lwop_rule_set_id', $id)->count()
            : 0;
    }

    /** Cutoffs already generated on/after the date keep their figures until regenerated. */
    private function regenerateNote(string $effectiveFrom): string
    {
        $count = DB::table('cutoff_summaries')->where('period_start', '>=', $effectiveFrom)
            ->distinct()->count('cutoff_id');

        return $count > 0
            ? " {$count} cutoff(s) starting on/after {$effectiveFrom} were already generated; they keep their figures until you regenerate them."
            : '';
    }

    private function ruleSetsHaveAfterLwopColumns(): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 AS found FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [DB::getDatabaseName(), 'lwop_rule_sets', 'rest_day_rule']
        );
    }

    private function cutoffsTrackRuleSets(): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 AS found FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [DB::getDatabaseName(), 'cutoff_summaries', 'lwop_rule_set_id']
        );
    }
}
