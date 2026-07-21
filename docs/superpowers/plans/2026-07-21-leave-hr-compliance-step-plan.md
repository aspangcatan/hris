# Leave Application HR Compliance Step Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Applications filed with attachments get an extra HR
compliance-check step inserted before the Section Head, on top of the
existing final HR approval - full chain becomes HR_COMPLIANCE → SH → DH →
HR. Applications with no attachments are unaffected.

**Architecture:** Add a fourth `role` enum value (`HR_COMPLIANCE`) to
`leave_application_approvals`. Reuse the existing `HR` permission/office
for it. Replace the two hardcoded order-enforcement checks in
`LeaveApprovalService::updateApprovalStatus` with one generic
"all earlier `order_no` rows must be approved" rule. Keep the chain in
sync with attachment state via one new `syncComplianceStep()` method
called from all three attachment-mutating endpoints.

**Tech Stack:** Laravel 8, MySQL (`hris` schema, `leave_application_approvals`
table is MyISAM), Blade + jQuery frontend, PHPUnit feature tests with
`DatabaseTransactions`.

**Design doc:** `docs/superpowers/specs/2026-07-21-leave-hr-compliance-step-design.md`

---

### Task 1: Add HR_COMPLIANCE to the role enum

**Files:**
- Create: `database/migrations/2026_07_21_090000_add_hr_compliance_role_to_leave_application_approvals.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the HR_COMPLIANCE role used by the leave HR compliance-check
     * step - see
     * docs/superpowers/specs/2026-07-21-leave-hr-compliance-step-design.md.
     * It is inserted ahead of SH only for applications that have
     * attachments; applications without attachments keep the existing
     * SH -> DH -> HR chain untouched.
     */
    public function up()
    {
        DB::statement("ALTER TABLE leave_application_approvals MODIFY role ENUM('HR_COMPLIANCE', 'SH', 'DH', 'HR') NOT NULL");
    }

    public function down()
    {
        DB::statement("DELETE FROM leave_application_approvals WHERE role = 'HR_COMPLIANCE'");
        DB::statement("ALTER TABLE leave_application_approvals MODIFY role ENUM('SH', 'DH', 'HR') NOT NULL");
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate --path=database/migrations/2026_07_21_090000_add_hr_compliance_role_to_leave_application_approvals.php`
Expected: `Migrating: 2026_07_21_090000_...` then `Migrated:  2026_07_21_090000_...`

- [ ] **Step 3: Verify the column accepts the new value**

Run:
```
php artisan tinker --execute="echo DB::table('information_schema.columns')->where('table_schema','hris')->where('table_name','leave_application_approvals')->where('column_name','role')->value('column_type');"
```
Expected: `enum('HR_COMPLIANCE','SH','DH','HR')`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_21_090000_add_hr_compliance_role_to_leave_application_approvals.php
git commit -m "feat: add HR_COMPLIANCE role for leave attachment compliance step"
```

---

### Task 2: Generalize approval order enforcement and permission checks

**Files:**
- Modify: `app/Services/LeaveApprovalService.php:48-53` (canApprovePendingStep)
- Modify: `app/Services/LeaveApprovalService.php:124-128,163-171,199-206` (getLeavesForApproval)
- Modify: `app/Services/LeaveApprovalService.php:313-344` (updateApprovalStatus order enforcement)
- Test: `tests/Feature/LeaveHrComplianceStepTest.php` (created in this task, extended in Task 5)

- [ ] **Step 1: Write a failing test for HR_COMPLIANCE permission + order enforcement**

Create `tests/Feature/LeaveHrComplianceStepTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveHrComplianceStepTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9171;
    private int $shUserId   = 9172;
    private int $dhUserId   = 9173;
    private int $hrUserId   = 9174;
    private int $sectionId  = 9271;
    private int $divisionId = 9272;
    private int $leaveTypeId;

    private string $sampleSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'Hrc', 'lname' => 'ShUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'hrc_sh_test', 'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'Hrc', 'lname' => 'DhUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'hrc_dh_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Hrc', 'lname' => 'EmpUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'hrc_emp_test', 'password' => 'x'],
            ['id' => $this->hrUserId,   'fname' => 'Hrc', 'lname' => 'HrUser', 'section' => 0, 'division' => 0, 'username' => 'hrc_hr_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'Hrc Test Division', 'head' => $this->dhUserId, 'code' => 'HRCDIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'Hrc Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'HRCSEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'HRCTEST'],
            ['description' => 'Hrc Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'HRCTEST')->value('id');

        DB::table('hris.leave_credits')->insertOrIgnore([
            'user_id' => $this->employeeId,
            'leave_id' => $this->leaveTypeId,
            'based_credit' => 10.000,
            'used_credit' => 0.000,
            'last_updated_month' => '2027-1',
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $appIds = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->pluck('id');
        DB::table('hris.leave_application_attachments')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_application_approvals')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $appIds)->delete();
        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->delete();
    }

    private function actAsEmployee(): void
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->employeeId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => false]);
    }

    private function actAsSh(): void
    {
        Auth::shouldReceive('id')->andReturn($this->shUserId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->shUserId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee', 'is_section_head' => true, 'is_division_head' => false]);
    }

    private function actAsHr(): void
    {
        Auth::shouldReceive('id')->andReturn($this->hrUserId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->hrUserId, 'section' => 0, 'division' => 0,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'hr', 'is_section_head' => false, 'is_division_head' => false]);
    }

    private function fileWithAttachment(): int
    {
        $this->actAsEmployee();
        $payload = [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
            'attachments' => [UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')],
        ];

        $response = $this->withoutMiddleware()->post('/insert_leave_applications', $payload);
        $response->assertOk();

        return DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');
    }

    /** @test */
    public function filing_with_an_attachment_creates_a_pending_hr_compliance_step_first(): void
    {
        $appId = $this->fileWithAttachment();

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)
            ->orderBy('order_no')
            ->pluck('role')
            ->toArray();

        $this->assertEquals(['HR_COMPLIANCE', 'SH', 'DH', 'HR'], $roles);

        $complianceOrderNo = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'HR_COMPLIANCE')->value('order_no');
        $this->assertEquals(1, $complianceOrderNo);
    }

    /** @test */
    public function sh_cannot_act_before_hr_compliance_approves(): void
    {
        $appId = $this->fileWithAttachment();

        $this->actAsSh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => $this->sampleSignature,
        ]);

        $response->assertStatus(500);
        $this->assertEquals('pending', DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'SH')->value('status'));
    }

    /** @test */
    public function hr_approving_compliance_lets_sh_act_next(): void
    {
        $appId = $this->fileWithAttachment();

        $this->actAsHr();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'Compliant',
        ])->assertOk();

        $this->assertEquals('approved', DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'HR_COMPLIANCE')->value('status'));

        $this->actAsSh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => $this->sampleSignature,
        ])->assertOk();

        $this->assertEquals('approved', DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'SH')->value('status'));
    }

    /** @test */
    public function hr_rejecting_compliance_rejects_the_whole_application_and_restores_credits(): void
    {
        $appId = $this->fileWithAttachment();

        $before = DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->value('based_credit');

        $this->actAsHr();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Rejected', 'remarks' => 'Attachment unreadable',
        ])->assertOk();

        $this->assertEquals('Rejected', DB::table('hris.leave_applications')->where('id', $appId)->value('status'));

        $after = DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->value('based_credit');
        $this->assertGreaterThan($before, $after);
    }

    /** @test */
    public function applications_without_attachments_keep_the_three_step_chain(): void
    {
        $this->actAsEmployee();
        $payload = [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-03-05', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
        ];
        $this->withoutMiddleware()->post('/insert_leave_applications', $payload)->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->orderBy('order_no')->pluck('role')->toArray();

        $this->assertEquals(['SH', 'DH', 'HR'], $roles);
    }
}
```

- [ ] **Step 2: Run the tests to see them fail**

Run: `php artisan test --filter=LeaveHrComplianceStepTest`
Expected: `filing_with_an_attachment_creates_a_pending_hr_compliance_step_first` and
`applications_without_attachments_keep_the_three_step_chain` FAIL (no
`HR_COMPLIANCE` role is ever produced yet - the insert path doesn't
create it and `syncComplianceStep` doesn't exist). The other three tests
also FAIL because HR can't act on a role that's never inserted.

- [ ] **Step 3: Generalize order enforcement in `updateApprovalStatus`**

In `app/Services/LeaveApprovalService.php`, replace lines 313-344 (from
the `DB::transaction(function ...) {` opening through the closing of the
second `if ($effectiveRole === 'HR') { ... }` block, right before
`$approvalUpdate = [`):

```php
        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus, $disapprovedDates, $signature, $requiresSignature) {
            // Enforce chain order generically: every earlier step (by
            // order_no) must already be approved before this role's step
            // can be acted on. Works for both the 3-step (SH/DH/HR) and
            // 4-step (HR_COMPLIANCE/SH/DH/HR) chains without knowing which
            // roles precede which - see
            // docs/superpowers/specs/2026-07-21-leave-hr-compliance-step-design.md.
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

            $approvalUpdate = [
```

- [ ] **Step 4: Add HR_COMPLIANCE to permission checks**

In `app/Services/LeaveApprovalService.php`, in `canApprovePendingStep`
(lines 48-50), change:

```php
        if ($pendingApproval->role === 'HR') {
            return $this->canActAsHr();
        }
```

to:

```php
        if ($pendingApproval->role === 'HR' || $pendingApproval->role === 'HR_COMPLIANCE') {
            return $this->canActAsHr();
        }
```

- [ ] **Step 5: Add HR_COMPLIANCE to visibility queries**

In `app/Services/LeaveApprovalService.php`, in `getLeavesForApproval`,
make these three changes (same pattern each time - `HR` becomes
`whereIn(..., ['HR', 'HR_COMPLIANCE'])`):

Line 126, inside the `pending` branch:
```php
                            $q->where('lp.role', 'HR')->where('lp.approver_id', 15);
```
becomes:
```php
                            $q->whereIn('lp.role', ['HR', 'HR_COMPLIANCE'])->where('lp.approver_id', 15);
```

Line 167, inside the `approved_by_stage` branch:
```php
                                ->where('lp2.role', 'HR')
```
becomes:
```php
                                ->whereIn('lp2.role', ['HR', 'HR_COMPLIANCE'])
```

Line 203, inside the `approved_all` branch:
```php
                                ->where('lp2.role', 'HR')
```
becomes:
```php
                                ->whereIn('lp2.role', ['HR', 'HR_COMPLIANCE'])
```

- [ ] **Step 6: Run the tests again**

Run: `php artisan test --filter=LeaveHrComplianceStepTest`
Expected: `applications_without_attachments_keep_the_three_step_chain`
now PASSES. The attachment-related tests still FAIL (filing doesn't
create the `HR_COMPLIANCE` row yet - that's Task 3/4).

- [ ] **Step 7: Commit**

```bash
git add app/Services/LeaveApprovalService.php tests/Feature/LeaveHrComplianceStepTest.php
git commit -m "feat: generalize leave approval order enforcement for HR_COMPLIANCE step"
```

---

### Task 3: Add `syncComplianceStep` to LeaveApplicationService

**Files:**
- Modify: `app/Services/LeaveApplicationService.php` (add new public method)
- Test: `tests/Feature/LeaveHrComplianceStepTest.php` (extended)

- [ ] **Step 1: Add failing tests for the sync method**

Append to `tests/Feature/LeaveHrComplianceStepTest.php`:

```php
    /** @test */
    public function sync_compliance_step_inserts_it_when_an_attachment_exists_and_none_does_yet(): void
    {
        $this->actAsEmployee();
        $payload = [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-03-08', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
        ];
        $this->withoutMiddleware()->post('/insert_leave_applications', $payload)->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        DB::table('hris.leave_application_attachments')->insert([
            'leave_application_id' => $appId,
            'file_path' => 'leave_attachments/manual.pdf',
            'original_name' => 'manual.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Services\LeaveApplicationService::class)->syncComplianceStep($appId);

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->orderBy('order_no')->pluck('role')->toArray();
        $this->assertEquals(['HR_COMPLIANCE', 'SH', 'DH', 'HR'], $roles);
    }

    /** @test */
    public function sync_compliance_step_removes_a_still_pending_step_once_attachments_are_gone(): void
    {
        $appId = $this->fileWithAttachment();
        DB::table('hris.leave_application_attachments')->where('leave_application_id', $appId)->delete();

        app(\App\Services\LeaveApplicationService::class)->syncComplianceStep($appId);

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->orderBy('order_no')->pluck('role')->toArray();
        $this->assertEquals(['SH', 'DH', 'HR'], $roles);
    }

    /** @test */
    public function sync_compliance_step_leaves_an_approved_step_in_place_even_if_attachments_are_removed(): void
    {
        $appId = $this->fileWithAttachment();

        $this->actAsHr();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'Compliant',
        ])->assertOk();

        DB::table('hris.leave_application_attachments')->where('leave_application_id', $appId)->delete();
        app(\App\Services\LeaveApplicationService::class)->syncComplianceStep($appId);

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->orderBy('order_no')->pluck('role')->toArray();
        $this->assertEquals(['HR_COMPLIANCE', 'SH', 'DH', 'HR'], $roles);
        $this->assertEquals('approved', DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'HR_COMPLIANCE')->value('status'));
    }
```

- [ ] **Step 2: Run to see them fail**

Run: `php artisan test --filter=LeaveHrComplianceStepTest`
Expected: the three new tests FAIL with an error that `syncComplianceStep`
does not exist.

- [ ] **Step 3: Implement `syncComplianceStep`**

In `app/Services/LeaveApplicationService.php`, add this public method
(after `updateStatus`, before `insertLeaveApplication`, so it reads
top-to-bottom as "status helpers, then the chain-sync helper, then the
big insert"):

```php
    /**
     * Keeps the HR_COMPLIANCE approval step in sync with whether the
     * application currently has any attachments - see
     * docs/superpowers/specs/2026-07-21-leave-hr-compliance-step-design.md.
     * Called after every attachment add/remove and once right after
     * filing. Never removes an already-approved compliance step, even if
     * every attachment is later deleted - no retroactive undo.
     */
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
            } elseif (!$hasAttachments && $compliance && strtolower($compliance->status) === 'pending') {
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

- [ ] **Step 4: Run the tests again**

Run: `php artisan test --filter=LeaveHrComplianceStepTest`
Expected: the three sync tests now PASS. The filing-path tests from Task
2 that require attachments to trigger this at filing time still FAIL
(that wiring is Task 4).

- [ ] **Step 5: Commit**

```bash
git add app/Services/LeaveApplicationService.php tests/Feature/LeaveHrComplianceStepTest.php
git commit -m "feat: add LeaveApplicationService::syncComplianceStep"
```

---

### Task 4: Wire syncComplianceStep into the attachment endpoints and expose it in the JSON response

**Files:**
- Modify: `app/Http/Controllers/LeaveApplicationController.php:200-257` (insertLeaveApplication)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:259-307` (addAttachments)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:309-329` (deleteAttachment)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:355-359` (getLeaveApplication)
- Test: `tests/Feature/LeaveHrComplianceStepTest.php` (extended)

- [ ] **Step 1: Confirm Task 2/3's filing-time tests are still red**

Run: `php artisan test --filter=LeaveHrComplianceStepTest`
Expected: `filing_with_an_attachment_creates_a_pending_hr_compliance_step_first`,
`sh_cannot_act_before_hr_compliance_approves`,
`hr_approving_compliance_lets_sh_act_next`, and
`hr_rejecting_compliance_rejects_the_whole_application_and_restores_credits`
still FAIL - `insertLeaveApplication` stores the attachment but never
calls `syncComplianceStep`.

- [ ] **Step 2: Call syncComplianceStep after filing**

In `app/Http/Controllers/LeaveApplicationController.php`, in
`insertLeaveApplication`, immediately after the closing `}` of the
`if ($request->hasFile('attachments')) { ... }` block (currently line
244) and before the `return response()->json([...` (currently line 246),
add:

```php
            $this->leaveApplicationService->syncComplianceStep($appId);

```

- [ ] **Step 3: Call syncComplianceStep after adding attachments**

In the same file, in `addAttachments`, immediately after the `foreach`
loop that stores new attachment rows (currently ending around line 301,
right before `return response()->json(['message' => 'Attachments uploaded.', ...`),
add:

```php
        $this->leaveApplicationService->syncComplianceStep($id);

```

- [ ] **Step 4: Call syncComplianceStep after deleting an attachment**

In the same file, `deleteAttachment` currently is:

```php
    public function deleteAttachment(Request $request, int $attachmentId)
    {
        try {
            $row = DB::table('leave_application_attachments')
                ->join('leave_applications', 'leave_applications.id', '=', 'leave_application_attachments.leave_application_id')
                ->where('leave_application_attachments.id', $attachmentId)
                ->where('leave_applications.user_id', \Illuminate\Support\Facades\Auth::id())
                ->first(['leave_application_attachments.id', 'leave_application_attachments.file_path']);

            if (!$row) {
                return response()->json(['error' => 'Attachment not found.'], 404);
            }

            \Illuminate\Support\Facades\Storage::disk('public')->delete($row->file_path);
            DB::table('leave_application_attachments')->where('id', $attachmentId)->delete();

            return response()->json(['message' => 'Attachment deleted.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
```

Replace it with (adds `leave_application_id` to the select, and syncs
after deleting):

```php
    public function deleteAttachment(Request $request, int $attachmentId)
    {
        try {
            $row = DB::table('leave_application_attachments')
                ->join('leave_applications', 'leave_applications.id', '=', 'leave_application_attachments.leave_application_id')
                ->where('leave_application_attachments.id', $attachmentId)
                ->where('leave_applications.user_id', \Illuminate\Support\Facades\Auth::id())
                ->first(['leave_application_attachments.id', 'leave_application_attachments.file_path', 'leave_application_attachments.leave_application_id']);

            if (!$row) {
                return response()->json(['error' => 'Attachment not found.'], 404);
            }

            \Illuminate\Support\Facades\Storage::disk('public')->delete($row->file_path);
            DB::table('leave_application_attachments')->where('id', $attachmentId)->delete();

            $this->leaveApplicationService->syncComplianceStep($row->leave_application_id);

            return response()->json(['message' => 'Attachment deleted.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
```

- [ ] **Step 5: Expose hr_compliance in the JSON response**

In the same file, in `getLeaveApplication`, change lines 355-359 from:

```php
            'approvals'       => [
                'sh' => $leave->approvals['SH'] ?? ['status' => 'pending', 'remarks' => ''],
                'dh' => $leave->approvals['DH'] ?? ['status' => 'pending', 'remarks' => ''],
                'hr' => $leave->approvals['HR'] ?? ['status' => 'pending', 'remarks' => ''],
            ],
```

to:

```php
            'approvals'       => array_filter([
                'hr_compliance' => $leave->approvals['HR_COMPLIANCE'] ?? null,
                'sh'            => $leave->approvals['SH'] ?? ['status' => 'pending', 'remarks' => ''],
                'dh'            => $leave->approvals['DH'] ?? ['status' => 'pending', 'remarks' => ''],
                'hr'            => $leave->approvals['HR'] ?? ['status' => 'pending', 'remarks' => ''],
            ]),
```

- [ ] **Step 6: Run all the new tests**

Run: `php artisan test --filter=LeaveHrComplianceStepTest`
Expected: all tests in the file PASS.

- [ ] **Step 7: Add a test for the JSON response exposing hr_compliance**

Append to `tests/Feature/LeaveHrComplianceStepTest.php`:

```php
    /** @test */
    public function get_leave_application_exposes_hr_compliance_only_when_it_exists(): void
    {
        $appId = $this->fileWithAttachment();

        $this->actAsEmployee();
        $response = $this->withoutMiddleware()->getJson("/leave-applications/{$appId}");
        $response->assertOk();
        $this->assertArrayHasKey('hr_compliance', $response->json('approvals'));
        $this->assertEquals('pending', $response->json('approvals.hr_compliance.status'));

        $this->actAsEmployee();
        $payload = [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-03-09', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
        ];
        $this->withoutMiddleware()->post('/insert_leave_applications', $payload)->assertOk();
        $noAttachmentAppId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)
            ->where('id', '!=', $appId)->value('id');

        $response2 = $this->withoutMiddleware()->getJson("/leave-applications/{$noAttachmentAppId}");
        $response2->assertOk();
        $this->assertArrayNotHasKey('hr_compliance', $response2->json('approvals'));
    }

    /** @test */
    public function deleting_the_last_attachment_before_sh_approves_removes_the_compliance_step(): void
    {
        $appId = $this->fileWithAttachment();
        $attachmentId = DB::table('hris.leave_application_attachments')->where('leave_application_id', $appId)->value('id');

        $this->actAsEmployee();
        $this->withoutMiddleware()->delete("/leave-application-attachments/{$attachmentId}")->assertOk();

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->orderBy('order_no')->pluck('role')->toArray();
        $this->assertEquals(['SH', 'DH', 'HR'], $roles);
    }
```

- [ ] **Step 8: Run the full test file once more**

Run: `php artisan test --filter=LeaveHrComplianceStepTest`
Expected: all tests PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/LeaveApplicationController.php tests/Feature/LeaveHrComplianceStepTest.php
git commit -m "feat: wire HR compliance step sync into leave attachment endpoints"
```

---

### Task 5: Regression check against the existing signature/disapproval test suites

**Files:** none (verification only)

- [ ] **Step 1: Run the full leave test suite**

Run: `php artisan test --filter=Leave`
Expected: all tests across `LeaveApplicationSignatureTest`,
`LeaveDateDisapprovalTest`, `LeavePrintSignatureRenderTest`, and
`LeaveHrComplianceStepTest` PASS - confirming the generalized order
enforcement in `updateApprovalStatus` didn't change behavior for
applications without a compliance step.

If anything fails, stop and investigate before continuing to Task 6 -
do not proceed with a red suite.

---

### Task 6: Frontend - show the HR Compliance step in the application/approval UI

**Files:**
- Modify: `resources/views/modals/leave.blade.php:189-202`
- Modify: `resources/views/js/leave_application.blade.php:1055-1071`
- Modify: `resources/views/js/leave_approval.blade.php:272-285`

- [ ] **Step 1: Add the (initially hidden) compliance step to the shared modal**

In `resources/views/modals/leave.blade.php`, change:

```html
                    <div class="steps d-flex justify-content-between position-relative">
                        <div class="step text-center" id="step1">
                            <div class="circle">1</div>
                            <div class="label">Supervisor</div>
                        </div>
                        <div class="step text-center" id="step2">
                            <div class="circle">2</div>
                            <div class="label">Division Head</div>
                        </div>
                        <div class="step text-center" id="step3">
                            <div class="circle">3</div>
                            <div class="label">Human Resource</div>
                        </div>
                    </div>
```

to:

```html
                    <div class="steps d-flex justify-content-between position-relative">
                        <div class="step text-center d-none" id="step0">
                            <div class="circle">1</div>
                            <div class="label">HR Compliance</div>
                        </div>
                        <div class="step text-center" id="step1">
                            <div class="circle">1</div>
                            <div class="label">Supervisor</div>
                        </div>
                        <div class="step text-center" id="step2">
                            <div class="circle">2</div>
                            <div class="label">Division Head</div>
                        </div>
                        <div class="step text-center" id="step3">
                            <div class="circle">3</div>
                            <div class="label">Human Resource</div>
                        </div>
                    </div>
```

`step0` starts hidden (`d-none`); the numbers stay static text (1/2/3)
since they're decorative - which steps are visible communicates the
sequence.

- [ ] **Step 2: Show/hide and update step0 in the filer's view (leave_application.blade.php)**

In `resources/views/js/leave_application.blade.php`, in `viewApplication`,
change:

```js
            updateStepStatus('#step1', data.approvals.sh);
            updateStepStatus('#step2', data.approvals.dh);
            updateStepStatus('#step3', data.approvals.hr);

            let remarksHtml = "";
            ["sh", "dh", "hr"].forEach(role => {
                if (data.approvals[role]?.remarks) {
                    const label = role === "sh" ? "Supervisor"
                        : role === "dh" ? "Division Head" : "Human Resource";
                    remarksHtml += `
                        <div class="mb-2">
                            <strong>${label}:</strong>
                            <p class="mb-0">${data.approvals[role].remarks}</p>
                        </div><hr>
                    `;
                }
            });
```

to:

```js
            $('#step0').toggleClass('d-none', !data.approvals.hr_compliance);
            if (data.approvals.hr_compliance) {
                updateStepStatus('#step0', data.approvals.hr_compliance);
            }
            updateStepStatus('#step1', data.approvals.sh);
            updateStepStatus('#step2', data.approvals.dh);
            updateStepStatus('#step3', data.approvals.hr);

            let remarksHtml = "";
            ["hr_compliance", "sh", "dh", "hr"].forEach(role => {
                if (data.approvals[role]?.remarks) {
                    const label = role === "hr_compliance" ? "HR (Compliance Check)"
                        : role === "sh" ? "Supervisor"
                        : role === "dh" ? "Division Head" : "Human Resource";
                    remarksHtml += `
                        <div class="mb-2">
                            <strong>${label}:</strong>
                            <p class="mb-0">${data.approvals[role].remarks}</p>
                        </div><hr>
                    `;
                }
            });
```

- [ ] **Step 3: Show/hide and update step0 in the approver's view (leave_approval.blade.php)**

In `resources/views/js/leave_approval.blade.php`, in `renderLeaveModal`,
change:

```js
        updateStepStatus('#step1', data.approvals.sh);
        updateStepStatus('#step2', data.approvals.dh);
        updateStepStatus('#step3', data.approvals.hr);

        let remarks = ["sh", "dh", "hr"].map(role => {
            let a = data.approvals[role];
            return a?.remarks ? `
            <div class="mb-2">
                <strong>${role.toUpperCase()}:</strong>
                <p class="mb-0">${a.remarks}</p>
                <small class="text-muted">Status: ${a.status}</small>
            </div>
        ` : "";
        }).join("") || `<p class="text-muted">No remarks yet.</p>`;
```

to:

```js
        $('#step0').toggleClass('d-none', !data.approvals.hr_compliance);
        if (data.approvals.hr_compliance) {
            updateStepStatus('#step0', data.approvals.hr_compliance);
        }
        updateStepStatus('#step1', data.approvals.sh);
        updateStepStatus('#step2', data.approvals.dh);
        updateStepStatus('#step3', data.approvals.hr);

        let remarks = ["hr_compliance", "sh", "dh", "hr"].map(role => {
            let a = data.approvals[role];
            if (!a?.remarks) return "";
            const label = role === "hr_compliance" ? "HR COMPLIANCE" : role.toUpperCase();
            return `
            <div class="mb-2">
                <strong>${label}:</strong>
                <p class="mb-0">${a.remarks}</p>
                <small class="text-muted">Status: ${a.status}</small>
            </div>
        `;
        }).join("") || `<p class="text-muted">No remarks yet.</p>`;
```

- [ ] **Step 4: Manual smoke check**

There is no JS test runner in this project, so verify by hand: file a
leave application with an attachment as an employee, confirm the modal's
step tracker now shows 4 steps with "HR Compliance" first and pending;
log in as HR and approve the compliance step; reopen the application as
the filer and confirm the compliance step shows approved and the tracker
now highlights Supervisor as the active step. Also file one application
with no attachment and confirm the tracker still shows exactly 3 steps
with "HR Compliance" absent.

- [ ] **Step 5: Commit**

```bash
git add resources/views/modals/leave.blade.php resources/views/js/leave_application.blade.php resources/views/js/leave_approval.blade.php
git commit -m "feat: show HR Compliance step in the leave application step tracker"
```
