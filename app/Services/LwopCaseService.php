<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * LWOP Settings: the weekend cases (table lwop_weekend_cases). Saturday and
 * Sunday are OFF; a weekend counts as present unless a case marks its
 * Saturday and/or Sunday as LWOP, given the status of the Friday before and
 * the Monday after. Until the table exists, HR's three cases apply.
 */
class LwopCaseService
{
    /** Statuses a case can give the Friday before / Monday after. */
    public const STATUSES = ['lwop', 'present', 'absent', 'paid_leave'];

    /** HR's examples (2026-11-10): LWOP Fri + present Mon = 3 days; present Fri + LWOP Mon = 1; LWOP Fri + Mon = 4. */
    public const HR_DEFAULT_CASES = [
        ['friday' => 'lwop', 'monday' => 'present', 'saturday' => true, 'sunday' => true],
        ['friday' => 'present', 'monday' => 'lwop', 'saturday' => false, 'sunday' => false],
        ['friday' => 'lwop', 'monday' => 'lwop', 'saturday' => true, 'sunday' => true],
    ];

    /** @var array<string, array{saturday: bool, sunday: bool}>|null */
    private static ?array $cache = null;

    /** Test hook: when set, cases() returns this map. */
    private static ?array $override = null;

    /** @return array<string, array{saturday: bool, sunday: bool}> 'friday|monday' => which weekend days are LWOP */
    public function cases(): array
    {
        if (self::$override !== null) {
            return self::$override;
        }
        if (self::$cache !== null) {
            return self::$cache;
        }

        $exists = (bool) DB::selectOne(
            'SELECT 1 AS found FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [DB::getDatabaseName(), 'lwop_weekend_cases']
        );

        $rows = $exists
            ? DB::table('lwop_weekend_cases')->get(['friday_status', 'monday_status', 'saturday_lwop', 'sunday_lwop'])
                ->map(fn($r) => [
                    'friday' => $r->friday_status,
                    'monday' => $r->monday_status,
                    'saturday' => (bool) $r->saturday_lwop,
                    'sunday' => (bool) $r->sunday_lwop,
                ])->all()
            : self::HR_DEFAULT_CASES;

        return self::$cache = self::map($rows);
    }

    /**
     * @param array<int, array{friday: string, monday: string, saturday: bool, sunday: bool}> $cases
     * @return array<string, array{saturday: bool, sunday: bool}>
     */
    public static function map(array $cases): array
    {
        $map = [];
        foreach ($cases as $case) {
            if (in_array($case['friday'] ?? null, self::STATUSES, true) && in_array($case['monday'] ?? null, self::STATUSES, true)) {
                $map[$case['friday'] . '|' . $case['monday']] = [
                    'saturday' => (bool) ($case['saturday'] ?? false),
                    'sunday' => (bool) ($case['sunday'] ?? false),
                ];
            }
        }

        return $map;
    }

    /** Forget the cached cases (after a save/delete, and between tests). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @param array|null $cases list of ['friday','monday','saturday','sunday']; null = stop overriding */
    public static function override(?array $cases): void
    {
        self::$override = $cases === null ? null : self::map($cases);
    }
}
