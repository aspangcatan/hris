<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MinuteConversionService
{
    public function computeDeduction(int $totalMinutes): float
    {
        $minuteConversions = DB::table('minute_conversions')
            ->pluck('points_deduction', 'minute');

        $fullHours        = intdiv($totalMinutes, 60);
        $remainingMinutes = $totalMinutes % 60;

        $deductionFor60        = $minuteConversions[60] ?? 0;
        $deductionForRemaining = $remainingMinutes > 0
            ? ($minuteConversions[$remainingMinutes] ?? 0)
            : 0;

        return round(($deductionFor60 * $fullHours) + $deductionForRemaining, 3);
    }
}
