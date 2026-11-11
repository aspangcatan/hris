<?php

namespace App\Services;

/**
 * How much each leave date row charged to each leave balance.
 *
 * A date row charges credits_deducted to its own leave_type_id and, when sick
 * leave ran out (CSC MC No. 41, s. 1998, Sec. 56), fallback_credits to
 * fallback_leave_type_id (vacation leave). Every place that gives credits back
 * or takes them again uses this, so both parts always move together.
 */
class LeaveDateCharges
{
    /** Columns a query must select for byType() to work. */
    public const COLUMNS = ['id', 'date_applied', 'leave_type_id', 'credits_deducted', 'fallback_leave_type_id', 'fallback_credits'];

    /**
     * @param  iterable<object> $rows leave_date_applied rows (with COLUMNS selected)
     * @return array<int, float> leave type id => credits charged to it
     */
    public static function byType(iterable $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            if (!empty($row->leave_type_id) && (float) $row->credits_deducted > 0) {
                $totals[(int) $row->leave_type_id] = ($totals[(int) $row->leave_type_id] ?? 0) + (float) $row->credits_deducted;
            }
            $fallbackType = $row->fallback_leave_type_id ?? null;
            $fallback = (float) ($row->fallback_credits ?? 0);
            if (!empty($fallbackType) && $fallback > 0) {
                $totals[(int) $fallbackType] = ($totals[(int) $fallbackType] ?? 0) + $fallback;
            }
        }

        return array_map(fn($amount) => round($amount, 3), $totals);
    }
}
