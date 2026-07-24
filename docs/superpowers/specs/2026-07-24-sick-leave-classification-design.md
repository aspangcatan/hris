# Sick Leave Reason + Classification Split — Design

## Purpose

Today, the leave application modal's "In case of Sick Leave" detail section has four mutually-exclusive radio options, each with its own required text box: In Hospital (Specify Illness), Out Patient (Specify Illness), Work-Related (Specify), Non-Work Related (Specify). This conflates two distinct pieces of information into one choice.

The official CS Form No. 6 print template (`resources/views/print/leave_form.blade.php`) already only prints In Hospital / Out Patient for Sick Leave — Work-Related / Non-Work Related as a fourth/fifth mutually-exclusive option was never wired into the printed form at all.

This change splits Sick Leave's detail into two coupled choices made together:
1. **Reason** (with required text box): In Hospital *or* Out Patient — pick one, type the illness.
2. **Classification** (no text box): Work-Related *or* Non-Work Related — pick one.

Both are required together when Sick Leave is selected. The printed form then shows the reason's label followed by the typed text and the classification in parentheses, e.g. `In Hospital (Specify Illness) sample details (Work-Related)`.

## Scope

Frontend-only change, confined to `resources/views/js/leave_application.blade.php`. No database migration, no backend controller/service change, no print-template change — `leave_details.detail`/`leave_details.extra` already flow straight through to the print template unchanged, so combining the classification into `extra` at submission time is sufficient.

Old, already-submitted Sick Leave records that used the previous four-option shape (e.g. `detail = "Work-Related (Specify)"`) are left as-is — no data migration. Viewing/printing such a record shows whatever was saved. Editing one will not find a matching reason radio (since "Work-Related (Specify)" is no longer one of the two reason options), so its text box loads blank and neither reason nor classification is pre-checked — the user must re-select before they can save the edit. This is accepted, not specially handled.

## Data Model

No schema changes. `leave_details` keeps its existing `detail` (string) and `extra` (string, nullable) columns, one row per unique leave type on the application (index-matched to leave type by insertion order, same as today).

For Sick Leave specifically, at submission time the frontend combines both choices into that same pair:
- `detail` = the selected reason's label (`"In Hospital (Specify Illness)"` or `"Out Patient (Specify Illness)"`), unchanged from today.
- `extra` = `"{typed illness text} ({classification label})"`, e.g. `"Fever (Work-Related)"`.

The print template's existing lookup (`$detailExtraMap['In Hospital (Specify Illness)']`) needs no change — it already echoes the label followed by `extra`, so the classification automatically appears in the printed parenthetical suffix.

## Frontend Design

All changes are in `resources/views/js/leave_application.blade.php`.

**`LEAVE_DETAIL_CONFIG`'s sick-leave entry** gains an optional `classification` property:

```js
{
    keywords: ['sick leave'],
    groupId:  'detail_sick',
    label:    'In case of Sick Leave:',
    options: [
        { label: 'In Hospital (Specify Illness)', hasExtra: true },
        { label: 'Out Patient (Specify Illness)', hasExtra: true },
    ],
    classification: {
        label:     'Classification:',
        groupName: 'detail_sick_classification',
        options:   ['Work-Related', 'Non-Work Related'],
    },
},
```

Only the sick-leave entry declares `classification`; the other three entries (Vacation/Special Privilege, Special Leave Benefits for Women, Study Leave) are untouched and keep behaving exactly as today.

**Disambiguating the two radio groups within one config's DOM subtree:** both the reason radios and the (new) classification radios live inside the same `#detail_sick` container (so they enable/disable together via the existing group-level opacity/pointer-events toggle in `refreshDetailsVisibility()`/`resetDetailsSection()` — no change needed to that mechanism). To keep them distinguishable, every reason-option radio gets `data-role="primary"` and every classification-option radio gets `data-role="classification"`. All *existing* generic logic that currently does `$group.find('input[type="radio"]:checked')` is re-scoped to `input[type="radio"][data-role="primary"]:checked` — since every existing config's only radio group already gets `data-role="primary"`, this is a no-op behavior change for Vacation/Women/Study, and correctly excludes the new classification radios from logic that isn't meant to see them.

**`renderDetailGroups()`:** when a config has `cfg.classification`, render its options as an additional radio group (same disabled/`opacity:0.4` styling as the rest of the section, no text input beside each option) underneath the reason options, preceded by the `classification.label` heading.

**Validation (`handleSaveLeave`):** a new check parallel to the existing `detailMissing`/`extraMissing` checks — for every config with `cfg.classification` whose group is currently enabled, require `input[data-role="classification"]:checked`; if missing, block submission with a toast ("Please specify the classification (Work-Related/Non-Work Related)"). The existing `detailMissing`/`extraMissing` checks continue to apply to the reason radio only (via the `data-role="primary"` scoping above) — classification never has `hasExtra`, so it's naturally excluded from the extra-text requirement.

**Submission (`handleSaveLeave`'s `details_map` builder):** after computing `detail`/`extra` from the checked `data-role="primary"` radio (unchanged logic otherwise), if `cfg.classification` exists, look up the checked `data-role="classification"` radio's label and append `" ({label})"` to `extra`.

**Editing (`editApplication`'s detail pre-fill):** when re-opening an existing application, the reason radio is matched by `dt.detail` as today (now scoped to `data-role="primary"`). If the matched config has `cfg.classification`, `dt.extra` is matched against `/^(.*)\s\((.+)\)$/`; if the captured group matches one of `cfg.classification.options`, the text box is set to the text before the parenthetical and the matching classification radio is checked. If `dt.extra` doesn't match that pattern (old-format record, or no classification saved), the whole `dt.extra` string loads into the text box as-is and no classification radio is pre-checked, per the "leave old records as-is" decision above.

## Error Handling

No new failure modes beyond the added classification-required validation, which follows the same toast-and-block pattern already used for every other required field in this form.

## Testing / Verification

No automated test suite exists on this project (manual verification only, per established project convention). Verification plan:
1. Open "File Leave", add a date for Sick Leave, confirm both the reason group (2 options + text boxes) and a new classification group (2 options, no text boxes) appear and are required.
2. Try to submit with only a reason chosen and no classification — confirm it's blocked with a clear error.
3. Submit a complete Sick Leave application (reason + text + classification) — confirm success, then print the application and confirm the PDF shows the reason label followed by the typed text and the classification in parentheses.
4. Edit that same application — confirm the reason, its typed text, and the classification radio are all correctly pre-filled from what was saved.
5. Confirm Vacation/Special Privilege, Special Leave Benefits for Women, and Study Leave detail sections are visually and functionally unchanged (regression check on the shared generic logic).
