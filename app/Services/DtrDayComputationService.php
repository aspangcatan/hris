<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DtrDayComputationService
{
    /**
     * Computes late/undertime/absence facts for one employee across a date
     * range. This is the single source of truth shared by printDTR (display)
     * and cutoff generation (payroll deduction) - see
     * docs/superpowers/specs/2026-07-08-dtr-payroll-unified-computation-design.md
     *
     * @param Collection $schedules Keyed by date string ('Y-m-d'), values are
     *   rows with time_in/time_in_latest/time_out/time_out_latest/break_out/
     *   break_in/type/code. Must include one buffer day before $startDate and
     *   one after $endDate for overnight-shift lookups to work at range edges.
     * @param Collection $logs dtr_logs rows (created_at, status) spanning
     *   $startDate minus one day through $endDate plus one day.
     * @param array $exemptions ['holidays' => [date => ['type'=>,'description'=>]],
     *   'leaves' => [date => true], 'hospitalOrders' => [date => true],
     *   'passSlips' => [date => true], 'absenceExemptDates' => [date => true]].
     *   IMPORTANT: 'absenceExemptDates' is a *different* set than the other
     *   three, used only for is_absent - it must be pre-computed by the
     *   caller as the union of leave dates, hospital-order dates, approved
     *   overtime dates, and *official-only* pass slip dates. This exactly
     *   mirrors DTRServices::buildPresentAbsentByRange()'s existing (and
     *   uncontested) exemption set, which differs from the late/undertime
     *   exemption set: it includes overtime (which never affects lateness)
     *   and only official pass slips (not personal ones, which do exempt
     *   lateness but were never treated as an absence exemption). Passing an
     *   empty array here is fine when the caller (printDTR) never reads the
     *   is_absent field.
     * @return Collection Keyed by date string => ['late_minutes' => int,
     *   'undertime_minutes' => int, 'is_absent' => bool, 'is_holiday' => bool,
     *   'exemption_reason' => ?string]
     */
    public function computeRange(
        int $employeeId,
        string $startDate,
        string $endDate,
        Collection $schedules,
        Collection $logs,
        array $exemptions,
        bool $skipAllAutomation = false
    ): Collection {
        $holidays = $exemptions['holidays'] ?? [];
        $leaves = $exemptions['leaves'] ?? [];
        $hospitalOrders = $exemptions['hospitalOrders'] ?? [];
        $passSlips = $exemptions['passSlips'] ?? [];
        $absenceExemptDates = $exemptions['absenceExemptDates'] ?? [];

        $expectedDates = $this->resolveExpectedWorkDates($schedules, $startDate, $endDate, $holidays);

        $results = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $currentDay = $date->toDateString();
            $results[$currentDay] = $this->computeDay(
                $currentDay,
                $schedules,
                $logs,
                $holidays,
                $leaves,
                $hospitalOrders,
                $passSlips,
                $skipAllAutomation,
                in_array($currentDay, $expectedDates, true),
                !empty($absenceExemptDates[$currentDay])
            );
        }

        return collect($results);
    }

    /**
     * Preserves DTRServices::buildPresentAbsentByRange()'s existing semantics
     * as-is (uncontested, not part of the late/undertime divergence this
     * service fixes): schedule-plotted dates if any exist in range, otherwise
     * every non-holiday weekday. OFF-coded schedule dates (rest days, e.g.
     * the Friday/weekend of a compressed work week) are additionally
     * excluded - a day the employee isn't scheduled to work can't be an
     * absence, the same way it's excluded from late/undertime via
     * $isOffSchedule in computeDay().
     */
    private function resolveExpectedWorkDates(Collection $schedules, string $startDate, string $endDate, array $holidays): array
    {
        if ($schedules->isNotEmpty()) {
            return $schedules->keys()
                ->filter(function ($date) use ($startDate, $endDate) {
                    return $date >= $startDate && $date <= $endDate;
                })
                ->reject(fn($date) => array_key_exists($date, $holidays))
                ->reject(fn($date) => $this->isOffScheduleCode($schedules[$date]->code ?? null))
                ->values()
                ->all();
        }

        $dates = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            if ($date->isWeekday() && !array_key_exists($date->toDateString(), $holidays)) {
                $dates[] = $date->toDateString();
            }
        }
        return $dates;
    }

    private function isOffScheduleCode(?string $code): bool
    {
        $code = trim((string) $code);
        return $code !== '' && strtoupper($code) === 'OFF';
    }

    /**
     * Break minutes actually overlapping the employee's plotted shift, e.g.
     * a 12:00-13:00 break inside an 08:00-17:00 shift = 60 minutes. Returns 0
     * if the schedule has no break configured. Verbatim from DTRController's
     * printDTR (formerly a closure, now a method).
     */
    private function getScheduledBreakMinutes($schedule, string $scheduleDate): int
    {
        if (!$schedule || empty($schedule->break_out) || empty($schedule->break_in)) {
            return 0;
        }

        $scheduleIn = Carbon::parse($scheduleDate . ' ' . $schedule->time_in);
        $scheduleOut = Carbon::parse($scheduleDate . ' ' . $schedule->time_out);
        if ($scheduleOut->lte($scheduleIn)) {
            $scheduleOut->addDay();
        }

        $breakOut = Carbon::parse($scheduleDate . ' ' . $schedule->break_out);
        $breakIn = Carbon::parse($scheduleDate . ' ' . $schedule->break_in);

        if ($breakOut->lt($scheduleIn)) {
            $breakOut->addDay();
        }
        if ($breakIn->lte($breakOut)) {
            $breakIn->addDay();
        }

        $effectiveBreakStart = $breakOut->copy()->max($scheduleIn);
        $effectiveBreakEnd = $breakIn->copy()->min($scheduleOut);

        if ($effectiveBreakEnd->lte($effectiveBreakStart)) {
            return 0;
        }

        return $effectiveBreakStart->diffInMinutes($effectiveBreakEnd);
    }

    /**
     * Extracted from DTRController::printDTR()'s per-day closure (lines
     * 886-1336 as of the pre-extraction version of that file). Presentational
     * fields (day_marker, planned_schedule text, off_label) are intentionally
     * not reproduced here - they stay in the controller. Everything that
     * feeds the actual late/undertime/absence NUMBERS is preserved exactly.
     */
    private function computeDay(
        string $currentDay,
        Collection $schedules,
        Collection $logs,
        array $holidays,
        array $leaves,
        array $hospitalOrders,
        array $passSlips,
        bool $skipAllAutomation,
        bool $isExpectedWorkDate,
        bool $isAbsenceExempt
    ): array {
        $isHoliday = array_key_exists($currentDay, $holidays);
        $hasLeave = array_key_exists($currentDay, $leaves);
        $hasHospitalOrder = !empty($hospitalOrders[$currentDay]);
        $hasApprovedPassSlip = !empty($passSlips[$currentDay]);
        $hasExcusedRecord = $hasLeave || $hasHospitalOrder || $hasApprovedPassSlip;
        $hasComputationExemption = $hasExcusedRecord;

        $exemptionReason = null;
        if ($hasLeave) {
            $exemptionReason = 'leave';
        } elseif ($hasHospitalOrder) {
            $exemptionReason = 'hospital_order';
        } elseif ($hasApprovedPassSlip) {
            $exemptionReason = 'pass_slip';
        } elseif ($isHoliday) {
            $exemptionReason = 'holiday';
        }

        $schedule = $schedules[$currentDay] ?? null;
        $isOffSchedule = false;
        $renderedMinutes = 0;

        if ($schedule) {
            $scheduleCode = trim((string) ($schedule->code ?? ''));
            if ($scheduleCode !== '' && strtoupper($scheduleCode) === 'OFF') {
                $isOffSchedule = true;
            }

            if (!$isOffSchedule) {
                $scheduledBreakMinutes = $this->getScheduledBreakMinutes($schedule, $currentDay);
                $scheduleInForSpan = Carbon::parse($currentDay . ' ' . $schedule->time_in);
                $scheduleOutForSpan = Carbon::parse($currentDay . ' ' . $schedule->time_out);
                if ($scheduleOutForSpan->lte($scheduleInForSpan)) {
                    $scheduleOutForSpan->addDay();
                }
                $scheduledSpanMinutes = $scheduleInForSpan->diffInMinutes($scheduleOutForSpan);
                $renderedMinutes = max($scheduledSpanMinutes - $scheduledBreakMinutes, 0);
            }
        }

        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $isAbsent = false;
        $firstIn = null;
        $lastOut = null;

        $dayLogs = $logs->filter(fn($log) => Carbon::parse($log->created_at)->toDateString() === $currentDay);

        if ($dayLogs->isNotEmpty()) {
            $amIns = [];
            $pmIns = [];
            $amOuts = [];
            $pmOuts = [];

            foreach ($dayLogs as $log) {
                $timeString = Carbon::parse($log->created_at)->format('H:i:s');

                if ($log->status === 'IN') {
                    if ($timeString < '12:00:00') {
                        $amIns[] = $timeString;
                    } else {
                        $pmIns[] = $timeString;
                    }
                } else {
                    if ($timeString < '13:00:00') {
                        $amOuts[] = $timeString;
                    } else {
                        $pmOuts[] = $timeString;
                    }
                }
            }

            $hasAmIn = !empty($amIns);
            $hasPmIn = !empty($pmIns);
            $hasAmOut = !empty($amOuts);
            $hasPmOut = !empty($pmOuts);

            $skipLateUndertimeComputation = $hasComputationExemption || $skipAllAutomation;

            $rawSched = $schedule;
            $isOvernightSchedule = false;
            if ($rawSched) {
                $rawSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                $rawSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                $isOvernightSchedule = $rawSchedOut->lte($rawSchedIn);
            }

            $previousDay = Carbon::parse($currentDay)->subDay()->toDateString();
            $prevSched = $schedules[$previousDay] ?? null;
            $previousDayIsOvernightSchedule = false;
            if ($prevSched) {
                $prevSchedIn = Carbon::parse($previousDay . ' ' . $prevSched->time_in);
                $prevSchedOut = Carbon::parse($previousDay . ' ' . $prevSched->time_out);
                $previousDayIsOvernightSchedule = $prevSchedOut->lte($prevSchedIn);
            }

            if ($rawSched && !$isOffSchedule) {
                if ($isOvernightSchedule) {
                    $nextDay = Carbon::parse($currentDay)->addDay()->toDateString();

                    $currentDayLogs = $logs->filter(fn($log) => Carbon::parse($log->created_at)->toDateString() === $currentDay);
                    $nextDayLogs = $logs->filter(fn($log) => Carbon::parse($log->created_at)->toDateString() === $nextDay);

                    $schedInWindowStart = Carbon::parse($currentDay . ' ' . $rawSched->time_in)->subHours(6);
                    $schedInWindowEnd = Carbon::parse($currentDay . ' 23:59:59');
                    $firstIn = $currentDayLogs
                        ->where('status', 'IN')
                        ->pluck('created_at')
                        ->filter(function ($createdAt) use ($schedInWindowStart, $schedInWindowEnd) {
                            $logTime = Carbon::parse($createdAt);
                            return $logTime->gte($schedInWindowStart) && $logTime->lte($schedInWindowEnd);
                        })
                        ->min();

                    if ($firstIn) {
                        $firstInTime = Carbon::parse($firstIn)->startOfMinute();
                        $schedOutWindowStart = $firstInTime->copy();
                        $schedOutWindowEnd = Carbon::parse($nextDay . ' ' . $rawSched->time_out)->addHours(8);

                        $lastOut = $nextDayLogs
                            ->where('status', 'OUT')
                            ->pluck('created_at')
                            ->filter(function ($createdAt) use ($schedOutWindowStart, $schedOutWindowEnd) {
                                $logTime = Carbon::parse($createdAt);
                                return $logTime->gte($schedOutWindowStart) && $logTime->lte($schedOutWindowEnd);
                            })
                            ->max();
                    }
                } else {
                    $calcSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                    $calcSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                    if ($calcSchedOut->lte($calcSchedIn)) {
                        $calcSchedOut->addDay();
                    }

                    $windowStart = $calcSchedIn->copy()->subHours(6);
                    $windowEnd = $calcSchedOut->copy()->addHours(8);

                    $scheduleMatchedLogs = $logs->filter(function ($log) use ($windowStart, $windowEnd) {
                        $logTime = Carbon::parse($log->created_at);
                        return $logTime->gte($windowStart) && $logTime->lte($windowEnd);
                    });

                    $firstIn = $scheduleMatchedLogs->where('status', 'IN')->min('created_at');
                    if ($firstIn) {
                        $firstInTime = Carbon::parse($firstIn)->startOfMinute();
                        $lastOut = $scheduleMatchedLogs
                            ->where('status', 'OUT')
                            ->pluck('created_at')
                            ->filter(fn($out) => Carbon::parse($out)->startOfMinute()->gte($firstInTime))
                            ->max();
                    }
                }
            }

            $hasTodayIn = ($hasAmIn || $hasPmIn);
            $hasTodayOut = ($hasAmOut || $hasPmOut);
            $isOvernightSpilloverOnly =
                !$hasTodayIn &&
                $hasTodayOut &&
                $hasAmOut &&
                !$hasPmOut &&
                $previousDayIsOvernightSchedule;

            $calcSched = null;
            if ($isOvernightSpilloverOnly) {
                $calcSched = null;
            } elseif ($rawSched) {
                $calcSched = $rawSched;
            }

            $hasIncompleteScheduledLogs =
                $rawSched &&
                !$isOffSchedule &&
                !$isOvernightSpilloverOnly &&
                ($hasTodayIn || $hasTodayOut || $firstIn || $lastOut) &&
                !($firstIn && $lastOut);

            if (!$skipLateUndertimeComputation && $hasIncompleteScheduledLogs) {
                $firstIn = null;
                $lastOut = null;

                $absentUndertimeMinutes = 0;
                if ($rawSched) {
                    $absentSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                    $absentSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                    if ($absentSchedOut->lte($absentSchedIn)) {
                        $absentSchedOut->addDay();
                    }

                    $absentUndertimeMinutes = $renderedMinutes > 0
                        ? (int) $renderedMinutes
                        : $absentSchedIn->diffInMinutes($absentSchedOut);
                }

                if ($absentUndertimeMinutes > 0) {
                    $undertimeMinutes += $absentUndertimeMinutes;
                }
            }

            $hasAmAttendance = ($hasAmIn || $hasAmOut);
            $hasPmAttendance = ($hasPmIn || $hasPmOut);
            $isFullDaySchedule = $rawSched
                && !$isOffSchedule
                && !$isOvernightSchedule
                && $renderedMinutes > 240;
            $hasBreakConfigured = $calcSched
                && !empty($calcSched->break_out)
                && !empty($calcSched->break_in);
            $isHalfDayMissingHalf = $isFullDaySchedule
                && $hasBreakConfigured
                && !$isOvernightSpilloverOnly
                && $firstIn
                && $lastOut
                && ($hasAmAttendance xor $hasPmAttendance);

            if (!$skipLateUndertimeComputation && $calcSched && !$hasApprovedPassSlip && $firstIn) {
                $lateMinutes = 0;
                $undertimeMinutes = 0;

                if ($firstIn) {
                    $firstInTime = Carbon::parse($firstIn)->startOfMinute();
                    if ($isHalfDayMissingHalf && !$hasAmAttendance) {
                        $schedIn = Carbon::parse($currentDay . ' ' . $calcSched->break_in);
                        $schedInLatest = $schedIn->copy();
                    } else {
                        $schedIn = Carbon::parse($currentDay . ' ' . $calcSched->time_in);
                        $schedInLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_in_latest);
                    }
                    if ($schedInLatest->lt($schedIn)) {
                        $schedInLatest->addDay();
                    }

                    if ($firstInTime->gt($schedInLatest)) {
                        $lateDiffMinutes = $schedInLatest->diffInMinutes($firstInTime);

                        if (!empty($calcSched->break_out) && !empty($calcSched->break_in)) {
                            $breakOutTime = Carbon::parse($currentDay . ' ' . $calcSched->break_out);
                            $breakInTime  = Carbon::parse($currentDay . ' ' . $calcSched->break_in);
                            if ($breakInTime->lte($breakOutTime)) {
                                $breakInTime->addDay();
                            }

                            $overlapStart = $schedInLatest->copy()->max($breakOutTime);
                            $overlapEnd   = $firstInTime->copy()->min($breakInTime);

                            if ($overlapEnd->gt($overlapStart)) {
                                $lateDiffMinutes = max(0, $lateDiffMinutes - $overlapStart->diffInMinutes($overlapEnd));
                            }
                        }

                        $lateMinutes += $lateDiffMinutes;
                    }
                }

                if ($lastOut) {
                    $lastOutTime = Carbon::parse($lastOut)->startOfMinute();

                    $schedIn = Carbon::parse($currentDay . ' ' . $calcSched->time_in);
                    $schedOut = Carbon::parse($currentDay . ' ' . $calcSched->time_out);
                    if ($schedOut->lte($schedIn)) {
                        $schedOut->addDay();
                    }
                    $isGliding = isset($calcSched->type) && trim(strtoupper($calcSched->type)) === 'GLIDING';

                    if ($isHalfDayMissingHalf && !$hasPmAttendance) {
                        $requiredOut = Carbon::parse($currentDay . ' ' . $calcSched->break_out);
                    } elseif ($isGliding && $firstIn && !($isHalfDayMissingHalf && !$hasAmAttendance)) {
                        $effectiveIn = Carbon::parse($firstIn)->startOfMinute();
                        $schedInLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_in_latest);
                        $schedOutLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_out_latest);
                        if ($schedInLatest->lt($schedIn)) {
                            $schedInLatest->addDay();
                        }
                        if ($schedOutLatest->lt($schedOut)) {
                            $schedOutLatest->addDay();
                        }

                        if ($effectiveIn->lte($schedIn)) {
                            $requiredOut = $schedOut->copy();
                        } elseif ($effectiveIn->lte($schedInLatest)) {
                            $offsetMinutes = $schedIn->diffInMinutes($effectiveIn);
                            $requiredOut = $schedOut->copy()->addMinutes($offsetMinutes);
                            if ($requiredOut->gt($schedOutLatest)) {
                                $requiredOut = $schedOutLatest->copy();
                            }
                        } else {
                            $requiredOut = $schedOutLatest->copy();
                        }
                    } elseif ($firstIn && !($isHalfDayMissingHalf && !$hasAmAttendance)) {
                        // Non-gliding (FIXED and any other type): a step, not a
                        // slide. Arriving at/before time_in earns the plain
                        // time_out. Any lateness at all - even inside the
                        // time_in..time_in_latest grace window - requires
                        // time_out_latest instead. Lateness itself is still
                        // penalized separately above, unaffected by this.
                        // For schedules where time_out == time_out_latest
                        // (the common case), this is a no-op vs. before.
                        $effectiveIn = Carbon::parse($firstIn)->startOfMinute();
                        $schedOutLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_out_latest);
                        if ($schedOutLatest->lt($schedOut)) {
                            $schedOutLatest->addDay();
                        }

                        $requiredOut = $effectiveIn->lte($schedIn) ? $schedOut->copy() : $schedOutLatest->copy();
                    } else {
                        $requiredOut = $schedOut;
                    }

                    if ($lastOutTime->lt($requiredOut)) {
                        $undertimeMinutes = $lastOutTime->diffInMinutes($requiredOut);

                        if (!empty($calcSched->break_out) && !empty($calcSched->break_in)) {
                            $breakOutTime = Carbon::parse($currentDay . ' ' . $calcSched->break_out);
                            $breakInTime  = Carbon::parse($currentDay . ' ' . $calcSched->break_in);
                            if ($breakInTime->lte($breakOutTime)) {
                                $breakInTime->addDay();
                            }

                            $overlapStart = $lastOutTime->copy()->max($breakOutTime);
                            $overlapEnd   = $requiredOut->copy()->min($breakInTime);

                            if ($overlapEnd->gt($overlapStart)) {
                                $undertimeMinutes = max(0, $undertimeMinutes - $overlapStart->diffInMinutes($overlapEnd));
                            }
                        }
                    }
                }

                if ($isHalfDayMissingHalf) {
                    $halfDayMinutes = (int) round($renderedMinutes / 2);
                    if ($halfDayMinutes > 0) {
                        if (!$hasAmAttendance) {
                            $lateMinutes += $halfDayMinutes;
                        } else {
                            $undertimeMinutes += $halfDayMinutes;
                        }
                    }
                }
            }
        }

        // Absence mirrors buildPresentAbsentByRange()'s existing semantics
        // exactly: an expected work date is "absent" unless it's covered by
        // $isAbsenceExempt (leave/hospital-order/overtime/official-pass-slip
        // - a *different*, wider set than $hasComputationExemption above) or
        // has both a first-in and last-out resolved for the day.
        if ($isExpectedWorkDate && !$isAbsenceExempt) {
            $isAbsent = !($firstIn && $lastOut);
        }

        return [
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'is_absent' => $isAbsent,
            'is_holiday' => $isHoliday,
            'exemption_reason' => $exemptionReason,
        ];
    }
}
