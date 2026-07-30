# Leave Application Credit Deduction via minute_conversions

## Problem

`LeaveApplicationService` currently deducts leave credits per application using a
flat constant:

```php
const CREDIT_RATE_PER_DAY = 1.25; // CSC standard: 1 day (8h) = 1.25 credits
$credits = round(($hours / 8) * self::CREDIT_RATE_PER_DAY, 3);
```

Separately, `LeaveCreditService::computeEarnedCredits()` already deducts points
from an employee's *monthly earned credits* based on late/undertime minutes,
using the `minute_conversions` table (`minute` 1–60 → `points_deduction`,
seeded as `minute * (1.25 / 600)`, i.e. `minute_conversions[60] = 0.125`).

The request: leave application filing/editing should deduct credits by
looking up `minute_conversions` instead of using the flat `1.25`/day rate.

## Scope

- **In scope:** `LeaveApplicationService::insertLeaveApplication()` and
  `updateLeaveApplication()` — all 6 call sites currently using
  `CREDIT_RATE_PER_DAY`.
- **Out of scope:** `LeaveMonetizationService` keeps its own separate
  `CREDIT_RATE_PER_DAY = 1.25` constant, untouched. It converts a credit
  balance to equivalent days for monetization payout — a different
  calculation from per-application deduction, and not part of this request.
- **Unaffected by construction:** credit *restoration* logic in
  `updateLeaveApplication()`, `LeaveApprovalService`, and
  `LeaveCancellationService` all read back the stored
  `leave_date_applied.credits_deducted` value rather than recomputing from a
  formula, so it needs no change regardless of which formula produced that
  value.

## Known behavior change

`leave_date_applied.hours` is an `integer` column, so `hours * 60` is always
an exact multiple of 60. Under the new formula this collapses to:

```
credits_deducted = hours * minute_conversions[60]   // = hours * 0.125
```

A full 8-hour day now deducts **1.0 credit** instead of the current
**1.25 credits** (a 20% reduction), since `minute_conversions[60] = 1.25/600
= 0.125/hour` vs. the old `1.25/8 = 0.15625/hour`. This rate change is
intentional and confirmed.

## Design

### New `App\Services\MinuteConversionService`

```php
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
```

This is lifted directly from the existing minute-lookup block in
`LeaveCreditService::computeEarnedCredits()` (lines 27–40), extracted so it's
independently callable. Minutes beyond 60 are never a missing-row/0
fallback — they're converted into a full-hour counter (each block priced at
`minute_conversions[60]`) plus one final lookup for the sub-60 remainder.
The `?? 0` fallback only guards a single missing row in that remainder
lookup, not the >60 case.

### `LeaveCreditService` refactor

`computeEarnedCredits()` calls
`$this->minuteConversionService->computeDeduction($totalLateUndertime)`
instead of doing the lookup inline. Pure extraction, no behavior change.

### `LeaveApplicationService` changes

- Constructor gains a `MinuteConversionService` dependency (Laravel
  container auto-resolves it, same as the existing controller injection
  pattern).
- `CREDIT_RATE_PER_DAY` constant is removed.
- All 6 call sites switch from
  `round(($hours / 8) * self::CREDIT_RATE_PER_DAY, 3)` to
  `$this->minuteConversionService->computeDeduction($hours * 60)`:
  - `insertLeaveApplication()`: balance validation, per-type deduction,
    per-date `credits_deducted`
  - `updateLeaveApplication()`: balance validation, per-type deduction,
    per-date `credits_deducted`
- LWOP handling is untouched: `$isLwop` continues to short-circuit all
  validation/deduction, `credits_deducted` stays `0` for LWOP rows.

## Error handling

Same as today's `LeaveCreditService` behavior — `?? 0` fallback if a minute
row is missing. No new exceptions introduced.

## Testing

- Unit test for `MinuteConversionService::computeDeduction()`: exact
  multiples of 60 (0 remainder), a case with remainder (for completeness,
  even though leave applications won't produce one), zero minutes.
- `LeaveCreditServiceTest.php`: verify still passes after the extraction
  (no behavior change expected).
- Leave application tests (filing, editing, insufficient-balance rejection,
  credit restore on cancel/disapproval): update expected credit values to
  the new `hours * 0.125` rate.
