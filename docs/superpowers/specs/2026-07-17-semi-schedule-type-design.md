# SEMI Schedule Type — Design

**Goal:** Add a third schedule type, `SEMI`, to the Schedule Master List module. SEMI is a hybrid of `FIXED` and `GLIDING`: like GLIDING, it has a MIN/MAX IN and MIN/MAX OUT window; unlike GLIDING (which slides the required "out" time proportionally to how late the employee arrived), SEMI is a step function — arriving at or before MIN IN requires MIN OUT, arriving any time after MIN IN (whether still within the MIN/MAX IN window or later than MAX IN) requires MAX OUT. No partial/proportional credit.

**Example:** MIN IN 7:00 AM, MAX IN 8:00 AM, MIN OUT 4:00 PM, MAX OUT 5:00 PM. Time-in at 7:10 AM (i.e. any time after 7:00 AM) requires an out of 5:00 PM, not a prorated 4:10 PM.

## Why this fits without schema changes

`schedule_master_lists` already has `time_in`, `time_in_latest`, `time_out`, `time_out_latest` (plus `break_out`/`break_in`), which GLIDING already reuses as its MIN IN / MAX IN / MIN OUT / MAX OUT bounds. SEMI reuses the exact same four columns — only the computation branch differs. No migration needed.

## Confirmed behavior

- **Lateness** is unaffected — for every schedule type (FIXED, GLIDING, SEMI), lateness is computed the same way today: minutes past `time_in_latest` (MAX IN), independent of type. This does not change.
- **Required "out" time** (the only thing SEMI changes):
  - `effective_in <= time_in` (MIN IN) → required-out = `time_out` (MIN OUT)
  - `effective_in > time_in` (any lateness at all, whether still inside the MIN/MAX IN window or past MAX IN entirely) → required-out = `time_out_latest` (MAX OUT)
  - Confirmed with user: arriving later than MAX IN still just resolves to MAX OUT for the required-out calculation — the separate lateness penalty already covers that case.
- SEMI shares GLIDING's existing half-day special case (when a half-day is missing its AM or PM attendance half, required-out becomes `break_out`) — this is an attendance-pattern concern, not specific to GLIDING vs. SEMI.

## Changes

**`resources/views/schedule_master_list.blade.php`** (lines 137-140) — add `<option value="SEMI">SEMI</option>` to the type dropdown, alongside the existing FIXED/GLIDING options. No new form fields; the existing MIN/MAX IN/OUT time fields already apply to every type.

**`app/Http/Controllers/ScheduleController.php`** — `saveScheduleMasterList()` currently validates `'type' => ['required']` with no value restriction (any string silently falls into FIXED-like behavior downstream). Tighten to `'type' => ['required', 'in:FIXED,GLIDING,SEMI']`.

**`app/Services/DtrDayComputationService.php`** (~lines 428-484) — replace the binary `$isGliding` boolean with a type read that distinguishes GLIDING vs. SEMI vs. neither, and add a SEMI branch:

```php
$schedType = isset($calcSched->type) ? trim(strtoupper($calcSched->type)) : 'FIXED';
$isGliding = $schedType === 'GLIDING';
$isSemi    = $schedType === 'SEMI';

if ($isHalfDayMissingHalf && !$hasPmAttendance) {
    $requiredOut = Carbon::parse($currentDay . ' ' . $calcSched->break_out);
} elseif ($isGliding && $firstIn && !($isHalfDayMissingHalf && !$hasAmAttendance)) {
    // existing sliding logic, unchanged
    ...
} elseif ($isSemi && $firstIn && !($isHalfDayMissingHalf && !$hasAmAttendance)) {
    $effectiveIn = Carbon::parse($firstIn)->startOfMinute();
    $schedOutLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_out_latest);
    if ($schedOutLatest->lt($schedOut)) { $schedOutLatest->addDay(); }

    $requiredOut = $effectiveIn->lte($schedIn) ? $schedOut->copy() : $schedOutLatest->copy();
} else {
    $requiredOut = $schedOut; // FIXED
}
```

Undertime computation downstream of `$requiredOut` (comparing against actual last-out, subtracting break overlap) is unchanged — it already works generically off whatever `$requiredOut` resolves to.

## Testing

Add/extend a DTR computation test covering: (a) SEMI time-in at/before MIN IN → required-out is MIN OUT, no undertime if they leave at MIN OUT; (b) SEMI time-in a few minutes after MIN IN but still within MAX IN → required-out is MAX OUT, undertime applies if they leave before MAX OUT; (c) SEMI time-in after MAX IN entirely → still required-out MAX OUT (plus separately marked late, unchanged behavior). Also add a settings-page UI check (temporary Blade-render assertion, per repo convention) confirming the SEMI option renders in the dropdown, then a manual smoke test creating a SEMI schedule and confirming it saves/loads correctly.
