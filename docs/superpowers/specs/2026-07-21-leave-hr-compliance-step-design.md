# Leave Application HR Compliance Step — Design

## Problem

Today every leave application follows a fixed 3-step approval chain: Section
Head (SH) → Division Head (DH) → HR (final approval). Applications that
include attachments need an extra check: HR should verify the attachments
are compliant *before* the application goes to SH, in addition to HR's
existing final-approval step.

Requested flow for applications with attachments:

```
File Application
  → Forward to HR for checking compliance
  → Forward to Section Head
  → Forward to Division Head
  → Forward to HR for last approval
```

Applications with no attachments keep today's exact 3-step flow, unchanged.

## Data model

`hris.leave_application_approvals.role` is currently:

```sql
enum('SH', 'DH', 'HR')
```

Add a fourth value, `HR_COMPLIANCE`, via migration:

```sql
ALTER TABLE leave_application_approvals
  MODIFY role ENUM('HR_COMPLIANCE', 'SH', 'DH', 'HR') NOT NULL;
```

`HR_COMPLIANCE` uses the same approver assignment as final `HR`
(`approver_id = 15`, gated by the existing `canActAsHr()` check — same
`HR`/`HRH` access right or `leave.approve_hr` permission). No new permission
is introduced.

## Approval chain construction

- **No attachments at filing time:** unchanged — `SH(1) → DH(2) → HR(3)`.
- **Has attachments at filing time:** `HR_COMPLIANCE(1) → SH(2) → DH(3) →
  HR(4)`.

Rather than branch at filing time, the chain is always created as the base
3-step (`SH(1)/DH(2)/HR(3)`), and a new `LeaveApplicationService::
syncComplianceStep(int $appId)` method reconciles it against current
attachment state. This one method is called from three places, so filing,
adding an attachment, and removing an attachment all funnel through the same
logic:

1. After `insertLeaveApplication` stores any attachments (filing).
2. After `addAttachments` stores new attachment rows.
3. After `deleteAttachment` removes an attachment row.

```php
public function syncComplianceStep(int $appId): void
{
    DB::transaction(function () use ($appId) {
        $hasAttachments = DB::table('leave_application_attachments')
            ->where('leave_application_id', $appId)
            ->exists();

        $compliance = DB::table('leave_application_approvals')
            ->where('leave_application_id', $appId)
            ->where('role', 'HR_COMPLIANCE')
            ->first(['id', 'status']);

        if ($hasAttachments && !$compliance) {
            DB::table('leave_application_approvals')
                ->where('leave_application_id', $appId)
                ->increment('order_no');

            DB::table('leave_application_approvals')->insert([
                'leave_application_id' => $appId,
                'approver_id'          => 15,
                'role'                 => 'HR_COMPLIANCE',
                'order_no'             => 1,
                'status'               => 'pending',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } elseif (!$hasAttachments && $compliance
            && strtolower($compliance->status) === 'pending') {
            DB::table('leave_application_approvals')
                ->where('id', $compliance->id)
                ->delete();

            DB::table('leave_application_approvals')
                ->where('leave_application_id', $appId)
                ->where('order_no', '>', 1)
                ->decrement('order_no');
        }
    });
}
```

Notes:
- If `HR_COMPLIANCE` was already **approved**, it is left in place even if
  every attachment is later deleted — no retroactive undo. This matches the
  codebase's existing pattern of explicit-only undo (`undoApproval`), never
  an automatic side effect of unrelated data changes.
- `leave_application_approvals` is MyISAM, so `DB::transaction` here is a
  no-op today (same known limitation as the rest of this module — see
  `deleteSalnRecord`'s transaction wrapper for the established precedent of
  wrapping anyway).
- This is safe against a rare race (SH approves between an attachment
  mutation and the sync call) because `syncComplianceStep` only ever removes
  a `HR_COMPLIANCE` row that is still `pending` — if SH has already
  approved, `HR_COMPLIANCE` must already be `approved` too (order is
  enforced, see below), so the removal branch never fires.

## Order enforcement (generalized)

`LeaveApprovalService::updateApprovalStatus` currently hardcodes two
prerequisite checks:

```php
if ($effectiveRole === 'DH') { /* require SH approved */ }
if ($effectiveRole === 'HR') { /* require SH and DH approved */ }
```

These are replaced with one generic rule that works for both the 3-step and
4-step chains without knowing about `HR_COMPLIANCE` explicitly:

```php
$myOrderNo = DB::table('hris.leave_application_approvals')
    ->where('leave_application_id', $leave_id)
    ->where('role', $effectiveRole)
    ->value('order_no');

$priorIncomplete = DB::table('hris.leave_application_approvals')
    ->where('leave_application_id', $leave_id)
    ->where('order_no', '<', $myOrderNo)
    ->whereRaw('LOWER(status) != ?', ['approved'])
    ->exists();

if ($priorIncomplete) {
    throw new \RuntimeException('A prior approval step must be completed first.');
}
```

Everything else in `updateApprovalStatus` stays keyed off the literal role
string, and is already correct for the new step:

- `$requiresSignature` only applies to `SH`/`DH` — `HR_COMPLIANCE` and final
  `HR` both remain signature-free (confirmed: HR already doesn't sign
  today).
- Rejecting at any step (including `HR_COMPLIANCE`) already goes through the
  existing generic reject path: `status = 'Rejected'`, credits restored via
  `restoreCredits()`. No new code needed — this is "reject outright," which
  is what compliance-check rejection should do.
- Only the literal `role === 'HR'` branch marks
  `leave_applications.status = 'Approved'`. Since `HR` is always
  constructed as the last row regardless of chain length, this stays
  correct unchanged.

## Permission / visibility surfaces

`LeaveApprovalService::canApprovePendingStep` and `getLeavesForApproval`
gain one more branch mirroring the existing `HR` handling, using the same
`canActAsHr()` check:

```php
if ($pendingApproval->role === 'HR_COMPLIANCE') {
    return $this->canActAsHr();
}
```

In `getLeavesForApproval`, each of the three query branches (`pending`,
`approved_by_stage`, `approved_all`) gets an `HR_COMPLIANCE` clause added
alongside the existing `HR` clause (same `canActAsHr` gate, same
`approver_id = 15`).

## Attachment endpoint changes

- `LeaveApplicationController::insertLeaveApplication`: after the existing
  attachment-storing loop, call
  `$this->leaveApplicationService->syncComplianceStep($appId)`
  unconditionally (cheap no-op when there were no attachments).
- `LeaveApplicationController::addAttachments`: same call after its
  attachment-storing loop.
- `LeaveApplicationController::deleteAttachment`: same call after deleting
  the attachment row. (`deleteAttachment` doesn't currently know the parent
  `leave_application_id` in scope — it will need to select it as part of
  the existing lookup query before deleting, so it has an `$appId` to pass
  in.)

No change to the "cannot modify attachments after supervisor approval" gate
in `addAttachments`. `deleteAttachment` still has no equivalent gate — this
is a pre-existing inconsistency, not something introduced or worsened by
this feature, and is left untouched.

## Read/JSON surfaces

`LeaveApplicationService::findById` already builds `$application->approvals`
keyed by raw DB role string (`SH`, `DH`, `HR`). `HR_COMPLIANCE` rows flow
through automatically — no change needed there.

`LeaveApplicationController::getLeaveApplication` currently hardcodes the
three lowercase keys it exposes:

```php
'approvals' => [
    'sh' => $leave->approvals['SH'] ?? ['status' => 'pending', 'remarks' => ''],
    'dh' => $leave->approvals['DH'] ?? ['status' => 'pending', 'remarks' => ''],
    'hr' => $leave->approvals['HR'] ?? ['status' => 'pending', 'remarks' => ''],
],
```

Add a fourth key, present **only when the row exists** (this is how the
frontend detects whether this particular application has a compliance
step at all):

```php
'approvals' => array_filter([
    'hr_compliance' => isset($leave->approvals['HR_COMPLIANCE'])
        ? $leave->approvals['HR_COMPLIANCE'] : null,
    'sh' => $leave->approvals['SH'] ?? ['status' => 'pending', 'remarks' => ''],
    'dh' => $leave->approvals['DH'] ?? ['status' => 'pending', 'remarks' => ''],
    'hr' => $leave->approvals['HR'] ?? ['status' => 'pending', 'remarks' => ''],
]),
```

The equivalent JSON-building code path used by the approver-facing leave
detail endpoint (`leave_approval` page's leave-detail fetch) needs the same
`hr_compliance` key added under the same "only if present" rule.

## UI changes

`resources/views/modals/leave.blade.php` (shared modal, included by
`leave_application`, `leave_approval`, `review_leave`, and
`settings/timetable`): add a new step element before the existing
"Supervisor" step:

```html
<div class="step text-center d-none" id="step0">
    <div class="circle">1</div>
    <div class="label">HR Compliance</div>
</div>
```

Starts hidden (`d-none`); JS shows it only when the loaded application has
an `hr_compliance` entry in `approvals`. The existing three steps keep
their DOM ids (`step1`/`step2`/`step3`) to minimize diff surface — their
circle numbers stay 1/2/3 visually when `step0` is hidden, and become
2/3/4 when it's shown. (Simplest correct option: leave the number labels as
static text "1"/"2"/"3" and don't attempt to renumber dynamically, since
they're purely decorative — the shown/hidden step order communicates
sequence either way.)

`resources/views/js/leave_application.blade.php` (`viewApplication`) and
`resources/views/js/leave_approval.blade.php` (`renderLeaveModal`): both
get the same two changes:

```js
const hasCompliance = !!data.approvals.hr_compliance;
$('#step0').toggleClass('d-none', !hasCompliance);
if (hasCompliance) {
    updateStepStatus('#step0', data.approvals.hr_compliance);
}
```

And the remarks-history loop extends its role list:

```js
["hr_compliance", "sh", "dh", "hr"].forEach(role => { ... })
```

with a label mapping addition: `"hr_compliance"` → `"HR (Compliance
Check)"`, alongside the existing `"Supervisor"` / `"Division Head"` /
`"Human Resource"` labels.

## Out of scope

- No change to the printed CS Form 6 leave form — the compliance step is
  not a signing assignatory and isn't shown there.
- No new permission — both HR steps share `canActAsHr()`.
- No segregation-of-duties restriction — the same HR user may perform both
  the compliance check and the final approval on the same application.
- `deleteAttachment`'s missing "locked after SH approval" gate is a
  pre-existing inconsistency, left untouched.
