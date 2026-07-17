# FIXED Schedule Bracket Computation — Design

**Supersedes the original plan in this document.** The original ask was to add a new `SEMI` schedule type (a hybrid of FIXED and GLIDING) to the Schedule Master List module. During design, we determined a cleaner fix: rather than adding a third type, change **FIXED** itself to use the intended bracket/step logic. No new type, no dropdown change, no validation change.

**Why:** 10 of the 11 existing FIXED schedules already have `time_in == time_in_latest` and `time_out == time_out_latest` (MIN = MAX for both bounds) — i.e. today's "flat" FIXED behavior is already what those schedules effectively express. The 11th (`9S`) has a pre-existing, unrelated data anomaly (`time_in` 09:00 later than `time_in_latest` 08:00) that only affects lateness, not required-out, and is untouched by this change since `9S`'s `time_out == time_out_latest` too. Changing FIXED's required-out computation to the bracket rule is therefore a no-op for every schedule that exists today, while giving FIXED real bracket behavior going forward if an admin ever configures a genuine out-time spread — which is exactly what a new SEMI type would have done anyway, making a separate type redundant.

## Confirmed behavior

- **Lateness**: unchanged for every type — always measured against `time_in_latest`, independent of type.
- **Required "out" time for FIXED** (previously always a flat `time_out`, ignoring `time_out_latest` entirely) becomes a step function, matching the rule the user described:
  - `effective_in <= time_in` → required-out = `time_out`
  - `effective_in > time_in` (any lateness at all, whether still inside the `time_in`..`time_in_latest` window or later than `time_in_latest` entirely) → required-out = `time_out_latest`
  - Confirmed with user: arriving later than `time_in_latest` still just resolves to `time_out_latest` for the required-out calculation — the separate lateness penalty already covers that case, matching GLIDING's existing equivalent behavior.
- GLIDING is completely untouched — its proportional sliding logic is unaffected.
- The existing half-day-missing-half special case is preserved exactly as it works for GLIDING today: missing the PM half uses `break_out`; missing the AM half falls back to the plain flat `time_out` (bracket logic does not apply to that specific combination, for either GLIDING or FIXED).

## Change

**`app/Services/DtrDayComputationService.php`** (~lines 428-465) — the previous unconditional `else { $requiredOut = $schedOut; }` branch (used for any non-GLIDING type, i.e. FIXED) is replaced with the bracket/step logic described above, guarded by the same `$firstIn && !($isHalfDayMissingHalf && !$hasAmAttendance)` condition GLIDING already uses, falling back to the old flat behavior only for that excluded half-day case.

No changes to: the Schedule Master List dropdown/form, `ScheduleController` validation, or any other file. This is a single, self-contained computation change.

## Testing

Unit test additions to `tests/Unit/DtrDayComputationServiceTest.php` (pure in-memory, no DB) covering FIXED with a genuine `time_out` != `time_out_latest` spread:
1. Arrival at/before `time_in` → required-out is `time_out`, undertime measured against it.
2. Arrival after `time_in` but still at/before `time_in_latest` → required-out jumps to `time_out_latest` (not a prorated value).
3. Arrival after `time_in_latest` entirely → required-out is still `time_out_latest`, and lateness is also nonzero (unchanged, separate computation).
4. Regression: a flat FIXED schedule (`time_in == time_in_latest`, `time_out == time_out_latest`) produces the same late/undertime numbers as before this change, confirming the no-op claim for existing data.
