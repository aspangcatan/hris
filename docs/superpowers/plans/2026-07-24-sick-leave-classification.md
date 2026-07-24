# Sick Leave Reason + Classification Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the Sick Leave detail section's four mutually-exclusive radio options into two coupled, required choices — a reason with required text (In Hospital / Out Patient) and a classification with no text (Work-Related / Non-Work Related) — combined into the existing `detail`/`extra` submission fields so the backend, database, and print template need no changes.

**Architecture:** Single-file frontend change to `resources/views/js/leave_application.blade.php`. Extends the existing generic `LEAVE_DETAIL_CONFIG`-driven detail-section system with an optional `classification` sub-group per config entry, disambiguated from the primary reason radios via a `data-role` attribute so all existing generic validation/collection logic (shared by the other three leave-detail configs — Vacation, Women's leave, Study leave) continues to work unchanged for everything except Sick Leave.

**Tech Stack:** Laravel 8 Blade + jQuery + Bootstrap 5. No automated test suite exists on this project (no separate test database — verification is manual via structural code review, static checks, and a live UI walkthrough).

**Reference spec:** `docs/superpowers/specs/2026-07-24-sick-leave-classification-design.md`

---

### Task 1: Split Sick Leave's detail group into reason + classification

**Files:**
- Modify: `resources/views/js/leave_application.blade.php`

This is one cohesive, tightly-coupled change across six spots in the same file (config, render, two validation checks, submission, edit pre-fill) — they must land together, since e.g. adding the `data-role` attribute without updating the selectors that read it would silently break existing leave types' validation. One task, one commit at the end, verified as a whole.

- [ ] **Step 1: Update the sick-leave `LEAVE_DETAIL_CONFIG` entry**

Find (currently around line 134-144):

```javascript
        {
            keywords: ['sick leave'],
            groupId:  'detail_sick',
            label:    'In case of Sick Leave:',
            options: [
                { label: 'In Hospital (Specify Illness)', hasExtra: true },
                { label: 'Out Patient (Specify Illness)', hasExtra: true },
                { label: 'Work-Related (Specify)', hasExtra: true },
                { label: 'Non-Work Related (Specify)', hasExtra: true },
            ],
        },
```

Replace with:

```javascript
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

- [ ] **Step 2: Add `data-role="primary"` to every existing option radio, and render the classification sub-group**

Find `renderDetailGroups()` (currently around line 165-186):

```javascript
    function renderDetailGroups() {
        const $container = $('#detail_groups_container');
        $container.empty();

        LEAVE_DETAIL_CONFIG.forEach(cfg => {
            const optionsHtml = cfg.options.map(opt => `
                <div class="form-check d-flex align-items-center mt-1">
                    <input class="form-check-input me-2" type="radio" name="${cfg.groupId}" disabled data-has-extra="${opt.hasExtra}">
                    <label class="form-check-label me-2">${opt.label}</label>
                    ${opt.hasExtra ? '<input class="form-control w-50" type="text" disabled placeholder="Required">' : ''}
                </div>
            `).join('');

            $container.append(`
                <div id="${cfg.groupId}" class="detail-group detail-disabled mt-3"
                     style="opacity:0.4; pointer-events:none;">
                    <div>${cfg.label}</div>
                    ${optionsHtml}
                </div>
            `);
        });
    }
```

Replace with:

```javascript
    function renderDetailGroups() {
        const $container = $('#detail_groups_container');
        $container.empty();

        LEAVE_DETAIL_CONFIG.forEach(cfg => {
            const optionsHtml = cfg.options.map(opt => `
                <div class="form-check d-flex align-items-center mt-1">
                    <input class="form-check-input me-2" type="radio" name="${cfg.groupId}" data-role="primary" disabled data-has-extra="${opt.hasExtra}">
                    <label class="form-check-label me-2">${opt.label}</label>
                    ${opt.hasExtra ? '<input class="form-control w-50" type="text" disabled placeholder="Required">' : ''}
                </div>
            `).join('');

            const classificationHtml = cfg.classification ? `
                <div class="small fw-semibold mt-2 mb-1">${cfg.classification.label}</div>
                ${cfg.classification.options.map(label => `
                    <div class="form-check d-flex align-items-center mt-1">
                        <input class="form-check-input me-2" type="radio" name="${cfg.classification.groupName}" data-role="classification" disabled>
                        <label class="form-check-label me-2">${label}</label>
                    </div>
                `).join('')}
            ` : '';

            $container.append(`
                <div id="${cfg.groupId}" class="detail-group detail-disabled mt-3"
                     style="opacity:0.4; pointer-events:none;">
                    <div>${cfg.label}</div>
                    ${optionsHtml}
                    ${classificationHtml}
                </div>
            `);
        });
    }
```

- [ ] **Step 3: Scope the existing `detailMissing`/`extraMissing` checks to `data-role="primary"`, and add a classification-required check**

Find (currently around line 352-375, inside `handleSaveLeave`):

```javascript
        // Every active detail group must have a radio selected
        const detailMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            return !$group.hasClass('detail-disabled')
                && $group.find('input[type="radio"]:checked').length === 0;
        });
        if (detailMissing) {
            is_valid = false;
            toastr.error("Please provide details for all selected leave types", "Required");
        }
        // When a radio with hasExtra is selected, its text input is required
        const extraMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            if ($group.hasClass('detail-disabled')) return false;
            const checked = $group.find('input[type="radio"]:checked');
            if (!checked.length) return false;
            if (checked.attr('data-has-extra') !== 'true') return false;
            const $extraInput = checked.siblings('input[type="text"]');
            return !$extraInput.length || !$extraInput.val().trim();
        });
        if (extraMissing) {
            is_valid = false;
            toastr.error("Please fill in the required detail text for your selected option", "Required");
        }
```

Replace with:

```javascript
        // Every active detail group must have its primary reason selected
        const detailMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            return !$group.hasClass('detail-disabled')
                && $group.find('input[type="radio"][data-role="primary"]:checked').length === 0;
        });
        if (detailMissing) {
            is_valid = false;
            toastr.error("Please provide details for all selected leave types", "Required");
        }
        // When a radio with hasExtra is selected, its text input is required
        const extraMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            if ($group.hasClass('detail-disabled')) return false;
            const checked = $group.find('input[type="radio"][data-role="primary"]:checked');
            if (!checked.length) return false;
            if (checked.attr('data-has-extra') !== 'true') return false;
            const $extraInput = checked.siblings('input[type="text"]');
            return !$extraInput.length || !$extraInput.val().trim();
        });
        if (extraMissing) {
            is_valid = false;
            toastr.error("Please fill in the required detail text for your selected option", "Required");
        }
        // Groups with a classification sub-group require it to be selected too
        const classificationMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            if (!cfg.classification) return false;
            const $group = $(`#${cfg.groupId}`);
            return !$group.hasClass('detail-disabled')
                && $group.find('input[data-role="classification"]:checked').length === 0;
        });
        if (classificationMissing) {
            is_valid = false;
            toastr.error("Please specify the classification (Work-Related/Non-Work Related)", "Required");
        }
```

- [ ] **Step 4: Scope the `details_map` builder to `data-role="primary"`, and append the classification to `extra`**

Find (currently around line 324-340, inside `handleSaveLeave`):

```javascript
        // Build per-leave-type detail map from active detail groups
        const details_map = {};
        leave_ids.forEach(lid => {
            const lt = LEAVE_TYPES.find(t => t.id === lid);
            if (!lt) return;
            const lower = lt.description.toLowerCase();
            const cfg = LEAVE_DETAIL_CONFIG.find(c => c.keywords.some(kw => lower.includes(kw)));
            if (!cfg) return;
            const $group = $(`#${cfg.groupId}`);
            if ($group.hasClass('detail-disabled')) return;
            const checked = $group.find('input[type="radio"]:checked');
            if (!checked.length) return;
            const $extraInput = checked.siblings('input[type="text"]');
            details_map[lid] = {
                detail: checked.siblings('label').text().trim(),
                extra:  ($extraInput.length ? $extraInput.val().trim() : '') || null,
            };
        });
```

Replace with:

```javascript
        // Build per-leave-type detail map from active detail groups
        const details_map = {};
        leave_ids.forEach(lid => {
            const lt = LEAVE_TYPES.find(t => t.id === lid);
            if (!lt) return;
            const lower = lt.description.toLowerCase();
            const cfg = LEAVE_DETAIL_CONFIG.find(c => c.keywords.some(kw => lower.includes(kw)));
            if (!cfg) return;
            const $group = $(`#${cfg.groupId}`);
            if ($group.hasClass('detail-disabled')) return;
            const checked = $group.find('input[type="radio"][data-role="primary"]:checked');
            if (!checked.length) return;
            const $extraInput = checked.siblings('input[type="text"]');
            let extra = ($extraInput.length ? $extraInput.val().trim() : '') || null;
            if (cfg.classification) {
                const classificationLabel = $group.find('input[data-role="classification"]:checked').siblings('label').text().trim();
                if (classificationLabel) {
                    extra = extra ? `${extra} (${classificationLabel})` : `(${classificationLabel})`;
                }
            }
            details_map[lid] = {
                detail: checked.siblings('label').text().trim(),
                extra:  extra,
            };
        });
```

- [ ] **Step 5: Scope the edit pre-fill loop to `data-role="primary"`, and reverse-parse the classification suffix**

Find (currently around line 998-1019, inside `editApplication`):

```javascript
            // Pre-fill detail radios using leave_id reconstructed from dates by index
            if (Array.isArray(data.details_per_type)) {
                data.details_per_type.forEach(dt => {
                    if (!dt.detail) return;
                    const lt = LEAVE_TYPES.find(t => t.id === dt.leave_id);
                    if (!lt) return;
                    const lower = lt.description.toLowerCase().trim();
                    const cfg   = LEAVE_DETAIL_CONFIG.find(c => c.keywords.some(kw => lower.includes(kw)));
                    if (!cfg) return;
                    const $group = $(`#${cfg.groupId}`);
                    if ($group.hasClass('detail-disabled')) return;
                    $group.find('input[type="radio"]').each(function () {
                        if ($(this).siblings('label').text().trim() === dt.detail) {
                            $(this).prop('checked', true);
                            if ($(this).attr('data-has-extra') === 'true' && dt.extra) {
                                $(this).siblings('input[type="text"]').val(dt.extra);
                            }
                            return false;
                        }
                    });
                });
            }
```

Replace with:

```javascript
            // Pre-fill detail radios using leave_id reconstructed from dates by index
            if (Array.isArray(data.details_per_type)) {
                data.details_per_type.forEach(dt => {
                    if (!dt.detail) return;
                    const lt = LEAVE_TYPES.find(t => t.id === dt.leave_id);
                    if (!lt) return;
                    const lower = lt.description.toLowerCase().trim();
                    const cfg   = LEAVE_DETAIL_CONFIG.find(c => c.keywords.some(kw => lower.includes(kw)));
                    if (!cfg) return;
                    const $group = $(`#${cfg.groupId}`);
                    if ($group.hasClass('detail-disabled')) return;
                    $group.find('input[type="radio"][data-role="primary"]').each(function () {
                        if ($(this).siblings('label').text().trim() === dt.detail) {
                            $(this).prop('checked', true);
                            if ($(this).attr('data-has-extra') === 'true' && dt.extra) {
                                let extraText = dt.extra;
                                if (cfg.classification) {
                                    const m = dt.extra.match(/^(.*)\s\((.+)\)$/);
                                    if (m && cfg.classification.options.includes(m[2])) {
                                        extraText = m[1].trim();
                                        $group.find('input[data-role="classification"]').each(function () {
                                            if ($(this).siblings('label').text().trim() === m[2]) {
                                                $(this).prop('checked', true);
                                            }
                                        });
                                    }
                                }
                                $(this).siblings('input[type="text"]').val(extraText);
                            }
                            return false;
                        }
                    });
                });
            }
```

- [ ] **Step 6: Verify**

Since this project has no automated test suite, verify via:
1. Read the full modified sections after editing to confirm brace/paren balance and that all five changes landed correctly.
2. Structural check: confirm `data-role="primary"` appears on every option radio across all four `LEAVE_DETAIL_CONFIG` entries (not just sick leave) after `renderDetailGroups()` runs — e.g. by tracing the template literal, since Vacation/Women/Study must keep working exactly as before.
3. Live UI walkthrough (WAMP/MySQL is running):
   - Open "File Leave", add a Sick Leave date. Confirm the reason group (2 options + text boxes) renders, and a new "Classification:" group (2 options, no text boxes) renders beneath it.
   - Try to submit with a reason chosen but no classification — confirm it's blocked with the new "Please specify the classification..." toast.
   - Fill in reason + text + classification, submit successfully.
   - Print that application (`printApplication`) and confirm the PDF's Sick Leave line shows the reason label, the typed text, and `(Work-Related)` or `(Non-Work Related)` appended.
   - Edit that same application — confirm the reason radio, its typed text, and the classification radio are all correctly restored.
   - Add a Vacation/Special Privilege Leave date and confirm its detail section (2 options, both with text boxes, no classification) still works exactly as before — regression check on the shared generic logic.

- [ ] **Step 7: Commit**

```bash
git add resources/views/js/leave_application.blade.php
git commit -m "feat: split sick leave detail into reason + classification"
```

---

## Self-Review Notes

- **Spec coverage:** all five spec requirements (config restructure, disambiguated rendering, required-together validation, combined submission format, edit-time reverse-parsing, old-record graceful degradation) map onto Steps 1-5 above.
- **No placeholders:** every step shows complete before/after code, not a description of what to change.
- **Type/name consistency:** `data-role="primary"`/`data-role="classification"` and `cfg.classification.{label,groupName,options}` are used identically across all five steps.
- **Backward compatibility:** Step 5's regex-based reverse-parse falls back to loading the raw `dt.extra` string into the text box (matching the spec's "leave old records as-is" decision) when the trailing `(...)` doesn't match a known classification label — e.g. an old In Hospital record with no classification suffix, or one whose extra text coincidentally ends in parentheses that aren't a classification, both fall through safely without throwing.
