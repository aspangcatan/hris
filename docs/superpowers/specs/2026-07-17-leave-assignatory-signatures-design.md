# Leave Application Assignatory Signatures — Design

**Goal:** Every party who signs off on a leave application (the filer/applicant, the Section Head, and the Division Head) captures a real signature — drawn by hand or uploaded as an image — at the moment they act, and it's persisted per application so the printed CS Form 6 always shows the actual signatures of everyone who signed it, without any manual step at print time.

**Supersedes:** the ephemeral, non-persisted "Add Signature" modal on the print preview page (commits `8f9d8d2`, `e0e4920`, and the VAWC/footnote follow-ups) — that modal is removed. Signature capture now happens upstream, at filing/editing/approving time, not at print time.

## Scope

Three signers, three capture points:

1. **Filer** — captured when filing a new leave application, and again on every edit to a still-pending application (before the Section Head has approved). Re-signing on edit is deliberate: it's proof the filer acknowledged and re-confirmed the current contents, and it forecloses the possibility of someone else silently altering a pending application without the filer's knowledge.
2. **Section Head (SH)** — captured only when SH's decision is **Approved**. Not required/captured on Reject.
3. **Division Head (DH)** — same as SH: captured only on **Approved**, not on Reject.

**HR is explicitly out of scope** — HR is the third step in the approval chain but does not sign the printed form, per user confirmation. `updateApprovalStatus` continues to work for HR exactly as it does today, with no signature involved.

All three signature captures are **mandatory** — the relevant submit action (file, edit-save, or approve) is blocked with a validation error if no signature is present.

## Database

Two new nullable `LONGTEXT` columns (base64 `data:image/png;base64,...` strings, no new tables):

- `leave_applications.applicant_signature` — set on insert, overwritten on every update while the application is still editable (i.e., before SH has approved — `updateLeaveApplication` already refuses edits past that point, per its existing `Cannot edit: this application has already been forwarded for approval.` guard).
- `leave_application_approvals.signature` — set on the SH or DH row when that role's status transitions to `approved`. Never set for HR. Never set on `rejected`/`pending`.

## Capture UI

Reuses the exact same draw-or-upload signature pad built for the (now-removed) print-page modal: a `signature_pad` canvas (CDN-loaded, same as before) with an "Upload Signature" override and a "Clear" action. This UI is duplicated (not shared as a partial, to keep each surface's Blade/JS self-contained per this codebase's existing per-view JS convention) into two places:

1. **`resources/views/modals/leave.blade.php`** (+ `resources/views/js/leave_application.blade.php`) — added inline in the filing form, above the SUBMIT button. Required for both `insertLeaveApplication` and `updateLeaveApplication` submissions (the same modal handles both flows already, driven by `editing_leave_id`).
2. **`resources/views/leave_approval` view/JS** (`leave_approval.blade.php`'s modal + its JS) — added inline in the approval modal. Visible only when the current viewer's pending role is SH or DH (hidden entirely during HR's turn, since HR never signs). Required only when the **Approve** button is used; the Reject button's flow is untouched.

On submit, the canvas is read via `signaturePad.toDataURL('image/png')` and included in the existing JSON payload sent to the corresponding endpoint — no new routes.

## Backend

- **`LeaveApplicationService::insertLeaveApplication()`** — accepts `applicant_signature` in `$data`, validated as required by the controller before this is called, stored on the new `leave_applications.applicant_signature` column at insert.
- **`LeaveApplicationService::updateLeaveApplication()`** — accepts `applicant_signature` in `$data`, required, overwrites the column on every update.
- **`LeaveApplicationController::insertLeaveApplication()` / `updateLeaveApplication()`** — add `applicant_signature` to their validation rules (`required|string`).
- **`LeaveApprovalService::updateApprovalStatus()`** — gains a `?string $signature = null` parameter. When `$effectiveRole` is `SH` or `DH` and `$normalizedStatus === 'approved'`, the signature is required (throws if missing) and stored on that role's row alongside the existing `status`/`remarks`/`approved_at` update. For any other combination (reject, HR, pending), the parameter is ignored entirely — no validation, no storage.
- **`LeaveApplicationController::updateApprovalStatus()`** — reads `$request->signature`, passes it through; validation requiring it is conditional (only when the resolved current role is SH/DH and status is Approved — mirrors the service's own guard so the error surfaces before the transaction even opens).

## Print

**`PrintController::printLeaveForm()`** — select `applicant_signature` from `leave_applications`, and `signature` from the SH/DH rows in `leave_application_approvals` (already joined/queried for `$sectionHeadApproval`; add a similar fetch for the DH row, or extend the existing query). Pass all three to the view.

**`resources/views/print/leave_form.blade.php`**:
- Remove the entire "Add Signature" modal (markup, CSS, JS, CDN script tag) and the `#applicant_line` JS-insertion logic added in commits `8f9d8d2`/`e0e4920`.
- Render the applicant's stored signature (if present) into the existing `.applicant-line` div, reusing the `.signature-img` CSS class already defined there.
- Render the SH's stored signature into the 7.B Recommendation `(Authorized Officer)` slot, above the existing `$authorizedOfficerName` line.
- Render the DH's stored signature into the bottom `(Authorized Official)` slot, above the existing `$authorizedOfficialName` line.
- Any application filed/approved before this feature ships (or where the corresponding step hasn't happened yet, e.g. printing before DH has approved) simply shows the existing blank line for that slot — no error, no placeholder text.

## Testing

- Feature tests extending the existing `LeaveDateDisapprovalTest`-style pattern (real HTTP requests, MyISAM-safe manual cleanup where needed — note `leave_application_approvals`/`leave_applications` engine should be checked before any test mutates them) covering: filing without a signature is rejected; filing with one stores it; editing a pending application requires and overwrites the signature; approving as SH without a signature is rejected; approving as SH with one stores it on the SH row only; rejecting requires no signature; HR approving requires no signature and never touches the signature column.
- Print: a temporary Blade-render assertion confirming the three signature images render when present and the blank-line fallback renders when absent, plus confirmation the old print-page modal markup/JS is fully gone.
- Manual smoke test: file a leave application (drawing a signature), approve it as SH (drawing a different signature), approve as DH, print, and visually confirm all three signatures appear in their correct slots.
