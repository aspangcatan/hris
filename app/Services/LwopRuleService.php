<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Effective-dated LWOP / earned-credit rules (table lwop_rule_sets, LWOP
 * Settings page). The rule set for a date is the one with the latest
 * effective_from on or before it. Until the table exists (migration not run
 * yet) the coded defaults below are used, which equal the seeded base row.
 */
class LwopRuleService
{
    public const REST_DAY_SOURCES = ['schedule', 'weekend'];
    public const PARTIAL_DAY_MODES = ['fraction', 'whole'];
    public const FRACTIONAL_KEY_MODES = ['interpolate', 'floor', 'nearest'];
    /**
     * How rest days/holidays become LWOP:
     *  - long_runs: CSC Sec. 33 - only inside a continuous run longer than long_run_min_days;
     *  - cases:     HR's case table (lwop_rest_day_cases) - the status of the work day
     *               before and after the rest days decides whether they count.
     * The retired value 'after_lwop' is read as 'cases'.
     */
    public const REST_DAY_RULES = ['long_runs', 'cases'];

    /** Work-day statuses a case can name (before / after the rest days). */
    public const CASE_STATUSES = ['lwop', 'present', 'absent', 'paid_leave'];

    /** HR's examples (2026-11-10): LWOP Fri + present Mon = 3, present Fri + LWOP Mon = 1, LWOP Fri + Mon = 4. */
    public const HR_DEFAULT_CASES = [
        ['before' => 'lwop', 'after' => 'present', 'counts' => true],
        ['before' => 'present', 'after' => 'lwop', 'counts' => false],
        ['before' => 'lwop', 'after' => 'lwop', 'counts' => true],
    ];

    /** @var array<string, object> */
    private static array $cache = [];

    private static ?bool $tableExists = null;

    private static ?bool $casesTableExists = null;

    /** Test hook: when set, forDate() always returns this rule set. */
    private static ?object $override = null;

    public static function defaults(): object
    {
        return (object) [
            'id' => null,
            'effective_from' => '2000-01-01',
            'count_rest_days_in_long_runs' => true,
            'long_run_min_days' => 7,
            'rest_day_source' => 'schedule',
            'holidays_bridge_runs' => true,
            'partial_day_mode' => 'fraction',
            'partial_day_breaks_run' => true,
            'absences_count_without_pay' => true,
            'absences_join_runs' => true,
            'month_basis_days' => 30,
            'fractional_key_mode' => 'interpolate',
            'rest_day_rule' => 'long_runs',
            // Cases mode: a holiday next to rest days is one more rest day (else it ends the stretch).
            'after_lwop_includes_holidays' => true,
            // Cases mode: ['before|after' => counts_as_lwop]; a combination not listed does not count.
            'cases' => self::caseMap(self::HR_DEFAULT_CASES),
        ];
    }

    public function forDate(string $date): object
    {
        if (self::$override !== null) {
            return self::$override;
        }
        if (isset(self::$cache[$date])) {
            return self::$cache[$date];
        }

        if (self::$tableExists === null) {
            self::$tableExists = self::tableExists('lwop_rule_sets');
            self::$casesTableExists = self::tableExists('lwop_rest_day_cases');
        }

        $row = self::$tableExists
            ? DB::table('lwop_rule_sets')->where('effective_from', '<=', $date)->orderByDesc('effective_from')->first()
            : null;

        if (!$row) {
            return self::$cache[$date] = self::defaults();
        }

        $cases = self::$casesTableExists
            ? self::casesFor((int) $row->id)
            : null;

        return self::$cache[$date] = self::normalize($row, $cases);
    }

    /** A rule set's cases as stored, in display order: [['before','after','counts','remarks'], ...]. */
    public static function casesFor(int $ruleSetId): array
    {
        return DB::table('lwop_rest_day_cases')
            ->where('lwop_rule_set_id', $ruleSetId)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['before_status', 'after_status', 'counts_as_lwop', 'remarks'])
            ->map(fn($c) => [
                'before' => $c->before_status,
                'after' => $c->after_status,
                'counts' => (bool) $c->counts_as_lwop,
                'remarks' => $c->remarks,
            ])
            ->all();
    }

    /**
     * Cast a DB row to the same shape as defaults() (booleans/ints, unknown values -> default).
     *
     * @param array|null $cases the rule set's cases (casesFor); null = cases table not there yet
     */
    public static function normalize(object $row, ?array $cases = null): object
    {
        $d = self::defaults();
        $pick = fn($value, array $allowed, $default) => in_array($value, $allowed, true) ? $value : $default;
        $restDayRule = ($row->rest_day_rule ?? null) === 'after_lwop' ? 'cases' : ($row->rest_day_rule ?? null);

        return (object) [
            'id' => (int) $row->id,
            'effective_from' => (string) $row->effective_from,
            'count_rest_days_in_long_runs' => (bool) $row->count_rest_days_in_long_runs,
            'long_run_min_days' => max(0, (int) $row->long_run_min_days),
            'rest_day_source' => $pick($row->rest_day_source, self::REST_DAY_SOURCES, $d->rest_day_source),
            'holidays_bridge_runs' => (bool) $row->holidays_bridge_runs,
            'partial_day_mode' => $pick($row->partial_day_mode, self::PARTIAL_DAY_MODES, $d->partial_day_mode),
            'partial_day_breaks_run' => (bool) $row->partial_day_breaks_run,
            'absences_count_without_pay' => (bool) $row->absences_count_without_pay,
            'absences_join_runs' => (bool) $row->absences_join_runs,
            'month_basis_days' => max(1, (int) $row->month_basis_days),
            'fractional_key_mode' => $pick($row->fractional_key_mode, self::FRACTIONAL_KEY_MODES, $d->fractional_key_mode),
            // Columns from migration 2026_11_10_300000; absent before it runs.
            'rest_day_rule' => $pick($restDayRule, self::REST_DAY_RULES, $d->rest_day_rule),
            'after_lwop_includes_holidays' => (bool) ($row->after_lwop_includes_holidays ?? $d->after_lwop_includes_holidays),
            // Cases table from migration 2026_11_10_400000; before it, HR's three cases.
            'cases' => $cases === null ? $d->cases : self::caseMap($cases),
        ];
    }

    /**
     * @param array<int, array{before: string, after: string, counts: bool}> $cases
     * @return array<string, bool> 'before|after' => counts as LWOP
     */
    public static function caseMap(array $cases): array
    {
        $map = [];
        foreach ($cases as $case) {
            $case = (array) $case;
            if (in_array($case['before'] ?? null, self::CASE_STATUSES, true) && in_array($case['after'] ?? null, self::CASE_STATUSES, true)) {
                $map[$case['before'] . '|' . $case['after']] = (bool) ($case['counts'] ?? false);
            }
        }

        return $map;
    }

    /** Forget cached rule sets (after a save/delete, and between tests). */
    public static function flush(): void
    {
        self::$cache = [];
        self::$tableExists = null;
        self::$casesTableExists = null;
    }

    /**
     * @param array|object|null $rules fields to change from the base rules; null = stop overriding.
     *        'cases' may be given as a list of ['before','after','counts'].
     */
    public static function override($rules): void
    {
        if ($rules === null) {
            self::$override = null;
        } else {
            $rules = (array) $rules;
            if (isset($rules['cases']) && self::isList($rules['cases'])) {
                $rules['cases'] = self::caseMap($rules['cases']);
            }
            self::$override = (object) array_merge((array) self::defaults(), $rules);
        }
        self::$cache = [];
    }

    private static function tableExists(string $table): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 AS found FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [DB::getDatabaseName(), $table]
        );
    }

    /** array_is_list() for PHP 7.4. */
    private static function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }
}
