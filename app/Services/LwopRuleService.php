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

    /** @var array<string, object> */
    private static array $cache = [];

    private static ?bool $tableExists = null;

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
            self::$tableExists = (bool) DB::selectOne(
                'SELECT 1 AS found FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [DB::getDatabaseName(), 'lwop_rule_sets']
            );
        }

        $row = self::$tableExists
            ? DB::table('lwop_rule_sets')->where('effective_from', '<=', $date)->orderByDesc('effective_from')->first()
            : null;

        return self::$cache[$date] = $row ? self::normalize($row) : self::defaults();
    }

    /** Cast a DB row to the same shape as defaults() (booleans/ints, unknown values -> default). */
    public static function normalize(object $row): object
    {
        $d = self::defaults();
        $pick = fn($value, array $allowed, $default) => in_array($value, $allowed, true) ? $value : $default;

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
        ];
    }

    /** Forget cached rule sets (after a save/delete, and between tests). */
    public static function flush(): void
    {
        self::$cache = [];
        self::$tableExists = null;
    }

    /** @param array|object|null $rules fields to change from the base rules; null = stop overriding */
    public static function override($rules): void
    {
        self::$override = $rules === null ? null : (object) array_merge((array) self::defaults(), (array) $rules);
        self::$cache = [];
    }
}
