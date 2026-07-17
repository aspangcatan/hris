# Leave Assignatory Signatures Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capture and persist a real signature (drawn or uploaded) for each of the three assignatories on a leave application — the filer at filing/edit time, the Section Head and Division Head at approval time — so the printed CS Form 6 always shows the actual signatures without any manual step at print time.

**Architecture:** Two new nullable `LONGTEXT` columns store base64 `data:image/png;base64,...` signature images: `leave_applications.applicant_signature` and `leave_application_approvals.signature`. A `signature_pad`-driven draw/upload canvas (reused pattern, CDN-loaded) is embedded inline in the existing filing modal and the existing approval modal — both live in the single shared partial `resources/views/modals/leave.blade.php`. Capture is mandatory: filing, editing a pending application, and SH/DH approving (not rejecting, not HR) all block on a missing signature. The print view renders whichever signatures are on file, falling back to a blank line when one isn't.

**Tech Stack:** Laravel 8 Blade + jQuery, `signature_pad` via CDN (already used and removed once this session — same library, same pattern). No new routes.

**Reference spec:** `docs/superpowers/specs/2026-07-17-leave-assignatory-signatures-design.md`

**Out of scope:** `resources/views/leave.blade.php`, `review_leave.blade.php`, `resources/views/js/leave.blade.php`, and `LeaveController::insertLeaves` are a separate, long-untouched legacy filing stack (different `LeaveFile` model, different controller) that happens to share the `modals/leave.blade.php` partial. The new signature UI will passively appear there too since the partial is shared, but won't be wired up (their own JS doesn't reference it) — acceptable, not addressed by this plan.

---

### Task 1: Migration — add signature columns

**Files:**
- Create: `database/migrations/2026_07_17_180000_add_signature_columns_to_leave_tables.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persists assignatory signatures per leave application - see
     * docs/superpowers/specs/2026-07-17-leave-assignatory-signatures-design.md.
     *
     * applicant_signature is captured at filing and re-captured on every
     * edit while the application is still pending SH approval. signature on
     * leave_application_approvals is only ever set for the SH and DH rows
     * when their status transitions to approved - HR never signs.
     */
    public function up()
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->longText('applicant_signature')->nullable()->after('commutation');
        });

        Schema::table('leave_application_approvals', function (Blueprint $table) {
            $table->longText('signature')->nullable()->after('remarks');
        });
    }

    public function down()
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('applicant_signature');
        });

        Schema::table('leave_application_approvals', function (Blueprint $table) {
            $table->dropColumn('signature');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_07_17_180000_add_signature_columns_to_leave_tables` then `Migrated:` with no errors.

- [ ] **Step 3: Verify the columns exist**

Run: `php artisan tinker --execute="echo implode(', ', Schema::getColumnListing('leave_applications')); echo PHP_EOL; echo implode(', ', Schema::getColumnListing('leave_application_approvals'));"`
Expected: first line includes `applicant_signature`, second line includes `signature`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_17_180000_add_signature_columns_to_leave_tables.php
git commit -m "feat: add signature columns to leave_applications and leave_application_approvals"
```

---

### Task 2: Backend — filer signature on insert/update

**Files:**
- Modify: `app/Services/LeaveApplicationService.php:84-91` (insertLeaveApplication's insertGetId call) and `:273-276` (updateLeaveApplication's final update call)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:212-221` (insertLeaveApplication's `$validator`) and `:407-416` (updateLeaveApplication's `$request->validate`)

- [ ] **Step 1: Add signature to the insert validation**

In `app/Http/Controllers/LeaveApplicationController.php`, find (around line 212-221):

```php
            $validator = \Illuminate\Support\Facades\Validator::make($params, [
                'details_map'               => 'nullable|array',
                'details_map.*.detail'      => 'nullable|string',
                'details_map.*.extra'       => 'nullable|string',
                'commutation'               => 'nullable|string|in:Requested,Not Requested',
                'dates'                     => 'required|array|min:1',
                'dates.*.date_applied'      => 'required|date',
                'dates.*.hours'             => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'     => 'required|integer',
            ]);
```

Replace with:

```php
            $validator = \Illuminate\Support\Facades\Validator::make($params, [
                'details_map'               => 'nullable|array',
                'details_map.*.detail'      => 'nullable|string',
                'details_map.*.extra'       => 'nullable|string',
                'commutation'               => 'nullable|string|in:Requested,Not Requested',
                'dates'                     => 'required|array|min:1',
                'dates.*.date_applied'      => 'required|date',
                'dates.*.hours'             => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'     => 'required|integer',
                'applicant_signature'       => 'required|string',
            ]);
```

- [ ] **Step 2: Add signature to the update validation**

In the same file, find (around line 407-416):

```php
            $validated = $request->validate([
                'details_map'                => 'nullable|array',
                'details_map.*.detail'       => 'nullable|string',
                'details_map.*.extra'        => 'nullable|string',
                'commutation'                => 'nullable|string|in:Requested,Not Requested',
                'dates'                      => 'required|array|min:1',
                'dates.*.date_applied'       => 'required|date',
                'dates.*.hours'              => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'      => 'required|integer',
            ]);
```

Replace with:

```php
            $validated = $request->validate([
                'details_map'                => 'nullable|array',
                'details_map.*.detail'       => 'nullable|string',
                'details_map.*.extra'        => 'nullable|string',
                'commutation'                => 'nullable|string|in:Requested,Not Requested',
                'dates'                      => 'required|array|min:1',
                'dates.*.date_applied'       => 'required|date',
                'dates.*.hours'              => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'      => 'required|integer',
                'applicant_signature'        => 'required|string',
            ]);
```

- [ ] **Step 3: Store the signature on insert**

In `app/Services/LeaveApplicationService.php`, find (around line 84-91):

```php
            // ONE leave_application record
            $appId = DB::table('leave_applications')->insertGetId([
                'user_id'     => Auth::id(),
                'commutation' => $data['commutation'] ?? null,
                'attachment'  => $data['attachment'] ?? null,
                'status'      => 'pending',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
```

Replace with:

```php
            // ONE leave_application record
            $appId = DB::table('leave_applications')->insertGetId([
                'user_id'             => Auth::id(),
                'commutation'         => $data['commutation'] ?? null,
                'attachment'          => $data['attachment'] ?? null,
                'applicant_signature' => $data['applicant_signature'],
                'status'              => 'pending',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
```

- [ ] **Step 4: Store the signature on update**

In the same file, find (around line 273-276):

```php
            DB::table('leave_applications')->where('id', $appId)->update([
                'commutation' => $data['commutation'] ?? null,
                'updated_at'  => now(),
            ]);
```

Replace with:

```php
            DB::table('leave_applications')->where('id', $appId)->update([
                'commutation'         => $data['commutation'] ?? null,
                'applicant_signature' => $data['applicant_signature'],
                'updated_at'          => now(),
            ]);
```

- [ ] **Step 5: Verify with a temporary tinker check**

Run: `php artisan tinker --execute="echo (new ReflectionMethod('App\Services\LeaveApplicationService', 'insertLeaveApplication'))->getName();"`
Expected: prints `insertLeaveApplication` with no fatal errors (confirms the file still parses cleanly after the edit).

Run: `php -l app/Services/LeaveApplicationService.php && php -l app/Http/Controllers/LeaveApplicationController.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Commit**

```bash
git add app/Services/LeaveApplicationService.php app/Http/Controllers/LeaveApplicationController.php
git commit -m "feat: require and store the applicant's signature on leave filing and edit"
```

---

### Task 3: Backend — SH/DH signature on approval

**Files:**
- Modify: `app/Services/LeaveApprovalService.php:288-367` (`updateApprovalStatus`)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:176-196` (`updateApprovalStatus`)

- [ ] **Step 1: Add the signature parameter and requirement guard to the service**

In `app/Services/LeaveApprovalService.php`, find:

```php
    public function updateApprovalStatus(int $leave_id, ?string $role = null, ?string $remarks = null, ?string $status = null, array $disapprovedDates = []): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leave_id)->first(['id']);
        if (!$app) {
            throw new \Exception('Leave application not found.');
        }

        $normalizedStatus = strtolower(trim($status ?? ''));
        $allowedStatuses  = ['pending', 'approved', 'rejected'];

        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid approval status.');
        }

        $effectiveRole = $role ?: $this->resolveCurrentApprovalRoleForLeave($leave_id);
        if ($effectiveRole === null) {
            throw new \RuntimeException('Current user is not allowed to approve this leave application.');
        }

        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus, $disapprovedDates) {
```

Replace with:

```php
    public function updateApprovalStatus(int $leave_id, ?string $role = null, ?string $remarks = null, ?string $status = null, array $disapprovedDates = [], ?string $signature = null): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leave_id)->first(['id']);
        if (!$app) {
            throw new \Exception('Leave application not found.');
        }

        $normalizedStatus = strtolower(trim($status ?? ''));
        $allowedStatuses  = ['pending', 'approved', 'rejected'];

        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid approval status.');
        }

        $effectiveRole = $role ?: $this->resolveCurrentApprovalRoleForLeave($leave_id);
        if ($effectiveRole === null) {
            throw new \RuntimeException('Current user is not allowed to approve this leave application.');
        }

        $requiresSignature = $normalizedStatus === 'approved' && in_array($effectiveRole, ['SH', 'DH'], true);
        if ($requiresSignature && empty($signature)) {
            throw new \InvalidArgumentException('A signature is required to approve this leave application.');
        }

        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus, $disapprovedDates, $signature, $requiresSignature) {
```

- [ ] **Step 2: Store the signature on the approval row's update**

In the same method, find:

```php
            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leave_id)
                ->where('role', $effectiveRole)
                ->update([
                    'status'      => $normalizedStatus,
                    'remarks'     => $remarks,
                    'approved_at' => Carbon::now(),
                    'updated_at'  => Carbon::now(),
                ]);
```

Replace with:

```php
            $approvalUpdate = [
                'status'      => $normalizedStatus,
                'remarks'     => $remarks,
                'approved_at' => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ];
            if ($requiresSignature) {
                $approvalUpdate['signature'] = $signature;
            }

            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leave_id)
                ->where('role', $effectiveRole)
                ->update($approvalUpdate);
```

- [ ] **Step 3: Pass the signature through the controller**

In `app/Http/Controllers/LeaveApplicationController.php`, find:

```php
    public function updateApprovalStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'disapproved_dates'                   => 'nullable|array',
                'disapproved_dates.*.date_applied_id' => 'required|integer',
                'disapproved_dates.*.remark'          => 'nullable|string|max:255',
            ]);

            $this->leaveApprovalService->updateApprovalStatus(
                $request->id,
                null,
                $request->remarks,
                $request->status,
                $validated['disapproved_dates'] ?? []
            );
            return response()->json(['message' => 'Update status done']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
```

Replace with:

```php
    public function updateApprovalStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'disapproved_dates'                   => 'nullable|array',
                'disapproved_dates.*.date_applied_id' => 'required|integer',
                'disapproved_dates.*.remark'          => 'nullable|string|max:255',
                'signature'                            => 'nullable|string',
            ]);

            $this->leaveApprovalService->updateApprovalStatus(
                $request->id,
                null,
                $request->remarks,
                $request->status,
                $validated['disapproved_dates'] ?? [],
                $validated['signature'] ?? null
            );
            return response()->json(['message' => 'Update status done']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
```

- [ ] **Step 4: Lint check**

Run: `php -l app/Services/LeaveApprovalService.php && php -l app/Http/Controllers/LeaveApplicationController.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Commit**

```bash
git add app/Services/LeaveApprovalService.php app/Http/Controllers/LeaveApplicationController.php
git commit -m "feat: require and store SH/DH signature when approving a leave application"
```

---

### Task 4: Backend regression tests for Tasks 2-3

**Files:**
- Create: `tests/Feature/LeaveApplicationSignatureTest.php`

This follows the exact pattern of `tests/Feature/LeaveDateDisapprovalTest.php` (same seed helpers, same `DatabaseTransactions` + manual `cleanup()` belt-and-suspenders approach, since `leave_applications` and `leave_application_approvals` are both MyISAM and transactions silently no-op on them).

- [ ] **Step 1: Write the test file**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveApplicationSignatureTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9161;
    private int $shUserId   = 9162;
    private int $dhUserId   = 9163;
    private int $sectionId  = 9261;
    private int $divisionId = 9262;
    private int $leaveTypeId;

    private string $sampleSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'Sig',  'lname' => 'ShUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sig_sh_test',  'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'Sig',  'lname' => 'DhUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sig_dh_test',  'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Sig',  'lname' => 'EmpUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sig_emp_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'Sig Test Division', 'head' => $this->dhUserId, 'code' => 'SIGDIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'Sig Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'SIGSEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'SIGTEST'],
            ['description' => 'Sig Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'SIGTEST')->value('id');

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

    private function actAsDh(): void
    {
        Auth::shouldReceive('id')->andReturn($this->dhUserId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->dhUserId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => true]);
    }

    private function filePayload(): array
    {
        return [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-02-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
        ];
    }

    /** @test */
    public function filing_without_a_signature_is_rejected(): void
    {
        $this->actAsEmployee();

        $payload = json_decode($this->filePayload()['data'], true);
        unset($payload['applicant_signature']);

        $response = $this->withoutMiddleware()->post('/insert_leave_applications', ['data' => json_encode($payload)]);

        $response->assertStatus(422);
        $this->assertEquals(0, DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->count());
    }

    /** @test */
    public function filing_with_a_signature_stores_it(): void
    {
        $this->actAsEmployee();

        $response = $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload());

        $response->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');
        $this->assertNotNull($appId);
        $this->assertEquals($this->sampleSignature, DB::table('hris.leave_applications')->where('id', $appId)->value('applicant_signature'));
    }

    /** @test */
    public function editing_a_pending_application_requires_and_overwrites_the_signature(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $newSignature = 'data:image/png;base64,ANOTHERONE==';
        $updateWithoutSignature = [
            'commutation' => 'Not Requested',
            'dates' => [
                ['date_applied' => '2027-02-02', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
            ],
        ];

        $this->withoutMiddleware()->putJson("/leave-applications/{$appId}", $updateWithoutSignature)
            ->assertStatus(422);

        $updateWithSignature = $updateWithoutSignature + ['applicant_signature' => $newSignature];
        $this->withoutMiddleware()->putJson("/leave-applications/{$appId}", $updateWithSignature)
            ->assertOk();

        $this->assertEquals($newSignature, DB::table('hris.leave_applications')->where('id', $appId)->value('applicant_signature'));
    }

    /** @test */
    public function sh_approving_without_a_signature_is_rejected(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId,
            'status' => 'Approved',
            'remarks' => 'Fine',
        ]);

        $response->assertStatus(500);
        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('signature'));
    }

    /** @test */
    public function sh_approving_with_a_signature_stores_it_on_the_sh_row_only(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $shSignature = 'data:image/png;base64,SHSIGNATURE==';
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId,
            'status' => 'Approved',
            'remarks' => 'Fine',
            'signature' => $shSignature,
        ])->assertOk();

        $this->assertEquals($shSignature, DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('signature'));
        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'DH')->value('signature'));
    }

    /** @test */
    public function rejecting_requires_no_signature(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId,
            'status' => 'Rejected',
            'remarks' => 'No good',
        ]);

        $response->assertOk();
    }

    /** @test */
    public function hr_approving_requires_no_signature_and_never_stores_one(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => 'data:image/png;base64,SH==',
        ])->assertOk();

        $this->actAsDh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => 'data:image/png;base64,DH==',
        ])->assertOk();

        Auth::shouldReceive('id')->andReturn(9999);
        Auth::shouldReceive('user')->andReturn((object) ['id' => 9999, 'section' => 0, 'division' => 0]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'hr']);

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok',
        ]);

        $response->assertOk();
        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'HR')->value('signature'));
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `php artisan test tests/Feature/LeaveApplicationSignatureTest.php`
Expected: `Tests: 7 passed`. If MySQL isn't running, start it via wampmanager first (WAMP tray icon, not `Start-Service` — needs elevation).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/LeaveApplicationSignatureTest.php
git commit -m "test: cover signature requirements for leave filing, editing, and SH/DH approval"
```

---

### Task 5: Frontend — signature capture UI (shared markup)

**Files:**
- Modify: `resources/views/modals/leave.blade.php`

- [ ] **Step 1: Add the signature_pad CDN script once, near the top of the file**

Find (line 1-2):

```html
<div class="modal fade premium-modal" id="leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
```

Replace with:

```html
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>

<div class="modal fade premium-modal" id="leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
```

- [ ] **Step 2: Add the filer's signature capture block to the filing modal**

Find (the Commutation block, immediately before the modal's footer buttons):

```html
                    <hr/>
                    <!-- Commutation — auto-set from leave type; read-only -->
                    <div class="fw-bold mt-4">Commutation</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="commutation" id="commutation_not_requested" disabled>
                                <label class="form-check-label" for="commutation_not_requested">Not Requested</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="commutation" id="commutation_requested" disabled>
                                <label class="form-check-label" for="commutation_requested">Requested</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">CLOSE</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_leave">SUBMIT</button>
                </div>
```

Replace with:

```html
                    <hr/>
                    <!-- Commutation — auto-set from leave type; read-only -->
                    <div class="fw-bold mt-4">Commutation</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="commutation" id="commutation_not_requested" disabled>
                                <label class="form-check-label" for="commutation_not_requested">Not Requested</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="commutation" id="commutation_requested" disabled>
                                <label class="form-check-label" for="commutation_requested">Requested</label>
                            </div>
                        </div>
                    </div>

                    <hr/>
                    <div class="d-flex align-items-center justify-content-between mt-4">
                        <div class="fw-bold">Applicant Signature</div>
                        <button type="button" class="btn btn-sm btn-link p-0" onclick="clearSignaturePad('applicant')">Clear</button>
                    </div>
                    <div class="small text-muted mb-2">Draw your signature below, or upload an image. Required to submit — filing or editing this application signs it as an acknowledgment of its current contents.</div>
                    <div style="border: 1.5px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; position: relative;">
                        <canvas id="applicant_signature_canvas" style="display:block; width:100%; height:150px;"></canvas>
                        <div id="applicant_signature_placeholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:10px; color:#94a3b8; font-size:12px; pointer-events:none;">Draw your signature here, or upload an image below</div>
                    </div>
                    <div class="mt-2">
                        <label class="btn btn-sm btn-outline-secondary mb-0" for="applicant_signature_upload">Upload Signature</label>
                        <input type="file" id="applicant_signature_upload" accept="image/*" class="d-none" onchange="handleSignatureUpload('applicant', event)">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">CLOSE</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_leave">SUBMIT</button>
                </div>
```

- [ ] **Step 3: Add the SH/DH signature capture block to the approval modal**

Find:

```html
                <div class="d-flex gap-2">
                    @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                        <button type="button" class="btn btn-danger" id="rejectLeaveBtn" value="Rejected">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <button type="button" class="btn btn-success" id="approveLeaveBtn" value="Approved">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                    @endif
                    <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
```

Replace with:

```html
                <div class="d-flex gap-2">
                    @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                        <button type="button" class="btn btn-danger" id="rejectLeaveBtn" value="Rejected">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <button type="button" class="btn btn-success" id="approveLeaveBtn" value="Approved">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                    @endif
                    <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
```

Note: the approval signature block goes *inside* the modal body, not the footer — find (within the same file, the Remarks Section block that ends the modal-body):

```html
                    @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                        <label for="leave_remarks" class="form-label"><strong>Add Your Remark:</strong></label>
                        <textarea id="leave_remarks"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Enter your remarks here..."></textarea>
                    @endif
                </div>
            </div>
```

Replace with:

```html
                    @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                        <label for="leave_remarks" class="form-label"><strong>Add Your Remark:</strong></label>
                        <textarea id="leave_remarks"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Enter your remarks here..."></textarea>

                        <div id="approver_signature_section" class="mt-3 d-none">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="form-label mb-0"><strong>Your Signature (required to Approve):</strong></label>
                                <button type="button" class="btn btn-sm btn-link p-0" onclick="clearSignaturePad('approver')">Clear</button>
                            </div>
                            <div style="border: 1.5px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; position: relative;">
                                <canvas id="approver_signature_canvas" style="display:block; width:100%; height:130px;"></canvas>
                                <div id="approver_signature_placeholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:10px; color:#94a3b8; font-size:12px; pointer-events:none;">Draw your signature here, or upload an image below</div>
                            </div>
                            <div class="mt-2">
                                <label class="btn btn-sm btn-outline-secondary mb-0" for="approver_signature_upload">Upload Signature</label>
                                <input type="file" id="approver_signature_upload" accept="image/*" class="d-none" onchange="handleSignatureUpload('approver', event)">
                            </div>
                        </div>
                    @endif
                </div>
            </div>
```

- [ ] **Step 4: Verify the file still renders**

Run: `php artisan view:clear`
Run a temporary test (write it, run it, delete it — matching this repo's established UI-verification convention):

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TempLeaveSignatureUiTest extends TestCase
{
    /** @test */
    public function the_filing_and_approval_modals_render_the_signature_capture_ui()
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId(1);

        $response = $this->get('/leave_application');
        $response->assertOk();
        $response->assertSee('applicant_signature_canvas', false);
        $response->assertSee('id="approver_signature_section"', false);
        $response->assertSee('signature_pad@4', false);
    }
}
```

Save to `tests/Feature/TempLeaveSignatureUiTest.php`, run:
Run: `php artisan test tests/Feature/TempLeaveSignatureUiTest.php`
Expected: `Tests: 1 passed`.
Then delete it: `rm tests/Feature/TempLeaveSignatureUiTest.php`

- [ ] **Step 5: Commit**

```bash
git add resources/views/modals/leave.blade.php
git commit -m "feat: add signature capture UI to the leave filing and approval modals"
```

---

### Task 6: Frontend — filing/edit signature JS wiring

**Files:**
- Modify: `resources/views/js/leave_application.blade.php`

- [ ] **Step 1: Add signature pad init/helper functions**

Find (near the top, after the `let editing_leave_id = null;` / modal declarations):

```javascript
    let page = 1;
    let leave_application_last_page = 1;
    let editing_leave_id = null;
    const file_leave_modal = new bootstrap.Modal(document.getElementById('file_leave_modal'));
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));
```

Replace with:

```javascript
    let page = 1;
    let leave_application_last_page = 1;
    let editing_leave_id = null;
    const file_leave_modal = new bootstrap.Modal(document.getElementById('file_leave_modal'));
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));

    // Signature pads, keyed by a short name ('applicant' here; 'approver' lives in js/leave_approval.blade.php).
    // Shared helpers so both files can reuse the same canvas/upload/clear mechanics.
    const SIGNATURE_PADS = {};
    const SIGNATURE_HAS_CONTENT = {};

    function initSignaturePad(key) {
        const canvas = document.getElementById(`${key}_signature_canvas`);
        if (!canvas) return null;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);

        const pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,0)' });
        pad.addEventListener('beginStroke', function () {
            SIGNATURE_HAS_CONTENT[key] = true;
            updateSignaturePlaceholder(key);
        });
        SIGNATURE_PADS[key] = pad;
        SIGNATURE_HAS_CONTENT[key] = false;
        return pad;
    }

    function updateSignaturePlaceholder(key) {
        const placeholder = document.getElementById(`${key}_signature_placeholder`);
        if (!placeholder) return;
        const hasContent = SIGNATURE_HAS_CONTENT[key] || (SIGNATURE_PADS[key] && !SIGNATURE_PADS[key].isEmpty());
        placeholder.style.display = hasContent ? 'none' : 'flex';
    }

    function clearSignaturePad(key) {
        const pad = SIGNATURE_PADS[key];
        if (!pad) return;
        pad.clear();
        SIGNATURE_HAS_CONTENT[key] = false;
        const upload = document.getElementById(`${key}_signature_upload`);
        if (upload) upload.value = '';
        updateSignaturePlaceholder(key);
    }

    function handleSignatureUpload(key, event) {
        const file = event.target.files[0];
        const pad = SIGNATURE_PADS[key];
        if (!file || !pad) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                const canvas = document.getElementById(`${key}_signature_canvas`);
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const w = canvas.width / ratio;
                const h = canvas.height / ratio;
                const scale = Math.min(w / img.width, h / img.height, 1);
                const drawW = img.width * scale;
                const drawH = img.height * scale;
                const x = (w - drawW) / 2;
                const y = (h - drawH) / 2;

                pad.clear();
                canvas.getContext('2d').drawImage(img, x, y, drawW, drawH);
                SIGNATURE_HAS_CONTENT[key] = true;
                updateSignaturePlaceholder(key);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function getSignatureDataUrl(key) {
        const pad = SIGNATURE_PADS[key];
        const hasContent = SIGNATURE_HAS_CONTENT[key] || (pad && !pad.isEmpty());
        if (!hasContent) return null;
        return pad.toDataURL('image/png');
    }
```

- [ ] **Step 2: Initialize the applicant pad and reset it whenever the filing modal opens (both "File" and "Edit")**

Find:

```javascript
        $("#btn_file_leave").click(async function () {
            clearForm();
            await init_leave();           // refresh balances & populate LEAVE_TYPES
            addRow();
            resetDetailsSection();
            refreshCommutationVisibility(); // set initial commutation state (no types checked yet → hidden)
            file_leave_modal.toggle();
        });
```

Replace with:

```javascript
        $("#btn_file_leave").click(async function () {
            clearForm();
            await init_leave();           // refresh balances & populate LEAVE_TYPES
            addRow();
            resetDetailsSection();
            refreshCommutationVisibility(); // set initial commutation state (no types checked yet → hidden)
            initApplicantSignaturePad();
            file_leave_modal.toggle();
        });
```

Find:

```javascript
    function clearForm() {
        editing_leave_id = null;
        $("#btn_save_leave").text("SUBMIT");
        $("#leave_form input").not("[type='radio'], [type='checkbox']").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
        $("#dates_table_body").empty();
        $("#range_leave_type").val('');
        $("#date_range_panel").addClass("d-none");
        $("#leave_attachment").val('');
        $("#new_files_preview").empty();
        $("#existing_attachments").addClass('d-none');
        $("#existing_attachments_list").empty();
        $("#attachment_section").hide();
        renderDetailGroups();
        refreshCommutationVisibility();
    }
```

Replace with:

```javascript
    function initApplicantSignaturePad() {
        // A fresh canvas every time the modal opens - editing an application
        // requires a brand-new signature each time, never a reused/stale one.
        initSignaturePad('applicant');
    }

    function clearForm() {
        editing_leave_id = null;
        $("#btn_save_leave").text("SUBMIT");
        $("#leave_form input").not("[type='radio'], [type='checkbox']").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
        $("#dates_table_body").empty();
        $("#range_leave_type").val('');
        $("#date_range_panel").addClass("d-none");
        $("#leave_attachment").val('');
        $("#new_files_preview").empty();
        $("#existing_attachments").addClass('d-none');
        $("#existing_attachments_list").empty();
        $("#attachment_section").hide();
        renderDetailGroups();
        refreshCommutationVisibility();
    }
```

- [ ] **Step 3: Initialize the pad when opening for Edit too**

Find (inside `editApplication`, right before `file_leave_modal.toggle();`):

```javascript
            renderExistingAttachments(existingAttachments);

            file_leave_modal.toggle();
        } catch {
            toastr.error("Error loading leave application for editing.", "Error");
        }
    }
```

Replace with:

```javascript
            renderExistingAttachments(existingAttachments);
            initApplicantSignaturePad();

            file_leave_modal.toggle();
        } catch {
            toastr.error("Error loading leave application for editing.", "Error");
        }
    }
```

- [ ] **Step 4: Validate and include the signature in the save payload**

Find:

```javascript
        if (!commutation) {
            is_valid = false;
            toastr.error("Please specify commutation", "Required");
        }
```

Replace with:

```javascript
        if (!commutation) {
            is_valid = false;
            toastr.error("Please specify commutation", "Required");
        }

        const applicantSignature = getSignatureDataUrl('applicant');
        if (!applicantSignature) {
            is_valid = false;
            toastr.error("Please draw or upload your signature", "Required");
        }
```

Find:

```javascript
        const params = {
            details_map,
            commutation,
            dates: date_applied_array,  // each item has { date_applied, hours, leave_type_id }
        };
```

Replace with:

```javascript
        const params = {
            details_map,
            commutation,
            dates: date_applied_array,  // each item has { date_applied, hours, leave_type_id }
            applicant_signature: applicantSignature,
        };
```

- [ ] **Step 5: Verify with a temporary browser-free sanity check**

Run: `php -l resources/views/js/leave_application.blade.php` is not applicable (this is a Blade file, not raw PHP) — instead run:
Run: `php artisan view:clear && php artisan test tests/Feature/LeaveDateDisapprovalTest.php`
Expected: `Tests: 9 passed` (confirms nothing in the shared leave view/JS broke the existing approval-flow regression suite, since these views are loaded together).

- [ ] **Step 6: Commit**

```bash
git add resources/views/js/leave_application.blade.php
git commit -m "feat: wire up applicant signature capture in the filing/edit modal"
```

---

### Task 7: Frontend — approval signature JS wiring

**Files:**
- Modify: `resources/views/js/leave_approval.blade.php`

Reuses the exact same `initSignaturePad` / `clearSignaturePad` / `handleSignatureUpload` / `getSignatureDataUrl` helpers defined in Task 6 — they're globally available since both JS files are loaded on their respective pages via the same shared `modals/leave.blade.php` partial pulling in the `signature_pad` CDN script, and `js/leave_approval.blade.php` only runs on the approval page (`leave_approval.blade.php`), which does **not** also load `js/leave_application.blade.php` — so these helper functions must be duplicated here too (each page only loads its own corresponding JS file). Copy the same four helper functions from Task 6 Step 1 into this file as well, using the `'approver'` key.

- [ ] **Step 1: Add the same signature helper functions**

Find (top of file):

```javascript
<script>
    let page = 1, leave_id = 0, leave_approval_last_page = 1, activeFilter = 'pending';
    const leaveApprovalViewButtonHtml = `<i class="bi bi-eye-fill text-success"></i>`;
```

Replace with:

```javascript
<script>
    let page = 1, leave_id = 0, leave_approval_last_page = 1, activeFilter = 'pending';
    let current_approval_role = null;
    const leaveApprovalViewButtonHtml = `<i class="bi bi-eye-fill text-success"></i>`;

    // Signature pad helpers — 'approver' key. Duplicated from js/leave_application.blade.php
    // since this page never loads that file (each page loads only its own JS companion).
    const SIGNATURE_PADS = {};
    const SIGNATURE_HAS_CONTENT = {};

    function initSignaturePad(key) {
        const canvas = document.getElementById(`${key}_signature_canvas`);
        if (!canvas) return null;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);

        const pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,0)' });
        pad.addEventListener('beginStroke', function () {
            SIGNATURE_HAS_CONTENT[key] = true;
            updateSignaturePlaceholder(key);
        });
        SIGNATURE_PADS[key] = pad;
        SIGNATURE_HAS_CONTENT[key] = false;
        return pad;
    }

    function updateSignaturePlaceholder(key) {
        const placeholder = document.getElementById(`${key}_signature_placeholder`);
        if (!placeholder) return;
        const hasContent = SIGNATURE_HAS_CONTENT[key] || (SIGNATURE_PADS[key] && !SIGNATURE_PADS[key].isEmpty());
        placeholder.style.display = hasContent ? 'none' : 'flex';
    }

    function clearSignaturePad(key) {
        const pad = SIGNATURE_PADS[key];
        if (!pad) return;
        pad.clear();
        SIGNATURE_HAS_CONTENT[key] = false;
        const upload = document.getElementById(`${key}_signature_upload`);
        if (upload) upload.value = '';
        updateSignaturePlaceholder(key);
    }

    function handleSignatureUpload(key, event) {
        const file = event.target.files[0];
        const pad = SIGNATURE_PADS[key];
        if (!file || !pad) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                const canvas = document.getElementById(`${key}_signature_canvas`);
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const w = canvas.width / ratio;
                const h = canvas.height / ratio;
                const scale = Math.min(w / img.width, h / img.height, 1);
                const drawW = img.width * scale;
                const drawH = img.height * scale;
                const x = (w - drawW) / 2;
                const y = (h - drawH) / 2;

                pad.clear();
                canvas.getContext('2d').drawImage(img, x, y, drawW, drawH);
                SIGNATURE_HAS_CONTENT[key] = true;
                updateSignaturePlaceholder(key);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function getSignatureDataUrl(key) {
        const pad = SIGNATURE_PADS[key];
        const hasContent = SIGNATURE_HAS_CONTENT[key] || (pad && !pad.isEmpty());
        if (!hasContent) return null;
        return pad.toDataURL('image/png');
    }
```

- [ ] **Step 2: Track the current role and show/init the signature section only for SH/DH**

Find:

```javascript
        $('#remarks_history').html(remarks);
        const canAct = activeFilter === 'pending';
        $('#approveLeaveBtn, #rejectLeaveBtn').toggle(canAct);
        $('#undoApprovalBtn').toggleClass('d-none', !data.can_undo);

        $('#viewLeaveModal').modal('show');
    }
```

Replace with:

```javascript
        $('#remarks_history').html(remarks);
        const canAct = activeFilter === 'pending';
        $('#approveLeaveBtn, #rejectLeaveBtn').toggle(canAct);
        $('#undoApprovalBtn').toggleClass('d-none', !data.can_undo);

        current_approval_role = data.current_approval_role;
        const needsSignature = canAct && (current_approval_role === 'SH' || current_approval_role === 'DH');
        $('#approver_signature_section').toggleClass('d-none', !needsSignature);
        if (needsSignature) {
            initSignaturePad('approver');
        }

        $('#viewLeaveModal').modal('show');
    }
```

- [ ] **Step 3: Require and send the signature only when approving as SH/DH**

Find:

```javascript
    function handleApproval() {
        const button = this;
        const originalHtml = button.innerHTML;
        $(button).prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        fetch('{{ route('updateApprovalStatus') }}', {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")},
            body: JSON.stringify({
                id: leave_id,
                remarks: $("#leave_remarks").val().trim(),
                status: this.value,
                disapproved_dates: collectDisapprovedDates()
            })
        })
            .then(r => r.json())
            .then(data => {
                toastr.success(data.message, "Info");
                $('#viewLeaveModal').modal('hide');
                getLeaves();
            })
            .catch(err => toastr.error(err.message || "Error", "Oops"))
            .finally(() => $(button).prop("disabled", false).html(originalHtml));
    }
```

Replace with:

```javascript
    function handleApproval() {
        const status = this.value;
        const requiresSignature = status === 'Approved' && (current_approval_role === 'SH' || current_approval_role === 'DH');
        const signature = requiresSignature ? getSignatureDataUrl('approver') : null;

        if (requiresSignature && !signature) {
            toastr.error("Please draw or upload your signature before approving", "Required");
            return;
        }

        const button = this;
        const originalHtml = button.innerHTML;
        $(button).prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        fetch('{{ route('updateApprovalStatus') }}', {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")},
            body: JSON.stringify({
                id: leave_id,
                remarks: $("#leave_remarks").val().trim(),
                status: status,
                disapproved_dates: collectDisapprovedDates(),
                signature: signature
            })
        })
            .then(r => r.json())
            .then(data => {
                toastr.success(data.message, "Info");
                $('#viewLeaveModal').modal('hide');
                getLeaves();
            })
            .catch(err => toastr.error(err.message || "Error", "Oops"))
            .finally(() => $(button).prop("disabled", false).html(originalHtml));
    }
```

- [ ] **Step 4: Verify with the existing regression suite**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php`
Expected: `Tests: 9 passed`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/js/leave_approval.blade.php
git commit -m "feat: wire up SH/DH signature capture in the leave approval modal"
```

---

### Task 8: Backend — fetch stored signatures for print

**Files:**
- Modify: `app/Http/Controllers/PrintController.php:333-357` (main query), `:386-389` (`$sectionHeadApproval`), `:436-454` (view data array)

- [ ] **Step 1: Select the applicant signature in the main query**

Find:

```php
            ->get([
                'p.basic_salary',
                'di.code as division',
                'di.description as division_name',
                's.code as section',
                's.description as section_name',
                'u.fname',
                'u.mname',
                'u.lname',
                'u.id as user_id',
                'u.section as section_id',
                'l.created_at',
                'd.description as designation',
                'u.division as division_id',
                'l.commutation',
                'l.id',
            ])
            ->first();
```

Replace with:

```php
            ->get([
                'p.basic_salary',
                'di.code as division',
                'di.description as division_name',
                's.code as section',
                's.description as section_name',
                'u.fname',
                'u.mname',
                'u.lname',
                'u.id as user_id',
                'u.section as section_id',
                'l.created_at',
                'd.description as designation',
                'u.division as division_id',
                'l.commutation',
                'l.applicant_signature',
                'l.id',
            ])
            ->first();
```

- [ ] **Step 2: Select SH and DH signatures**

Find:

```php
        $sectionHeadApproval = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $request->id)
            ->where('role', 'SH')
            ->first(['status', 'remarks']);
```

Replace with:

```php
        $sectionHeadApproval = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $request->id)
            ->where('role', 'SH')
            ->first(['status', 'remarks', 'signature']);

        $divisionHeadApproval = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $request->id)
            ->where('role', 'DH')
            ->first(['signature']);
```

- [ ] **Step 3: Pass the three signatures to the view**

Find:

```php
        return view('print.leave_form', [
            'leaveApplication'       => $leave_application,
```

Replace with:

```php
        return view('print.leave_form', [
            'applicantSignature'     => $leave_application->applicant_signature ?: null,
            'shSignature'            => $sectionHeadApproval->signature ?? null,
            'dhSignature'            => $divisionHeadApproval->signature ?? null,
            'leaveApplication'       => $leave_application,
```

- [ ] **Step 4: Lint check**

Run: `php -l app/Http/Controllers/PrintController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PrintController.php
git commit -m "feat: fetch stored applicant/SH/DH signatures for leave printing"
```

---

### Task 9: Frontend — render signatures on print, remove the ephemeral modal

**Files:**
- Modify: `resources/views/print/leave_form.blade.php`

This removes everything added in commits `8f9d8d2`, `e0e4920`, and the VAWC/footnote/width follow-ups that were specific to the ephemeral modal (the CSS classes `.sig-modal-*`, `.sig-canvas-*`, `.sig-row*`, `.sig-error`, `.sig-links`, the `#btn_open_signature` toolbar button, the `#sig_modal_overlay` markup block, and the trailing `<script>` block with `openSignatureModal`/`closeSignatureModal`/`clearSignatureCanvas`/`handleSignatureUpload`/`saveSignature`/the `signature_pad` CDN tag). The `.signature-img` / `.applicant-line .sign-line` CSS and the `.bottom-authority` CSS are **kept** — they're reused for the new server-rendered images.

- [ ] **Step 1: Remove the modal's CSS block**

Find (this immediately precedes the pre-existing, unrelated `@media print { .print-toolbar {...} }` rule — that one is kept):

```css
        .sig-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .sig-modal-overlay.open {
            display: flex;
        }

        .sig-modal {
            background: #fff;
            border-radius: 14px;
            width: 460px;
            max-width: 92vw;
            padding: 22px 22px 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #0f172a;
            position: relative;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }

        .sig-modal-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 26px;
            height: 26px;
            border: none;
            border-radius: 50%;
            background: #f1f5f9;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            color: #475569;
        }

        .sig-modal-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .sig-modal h3 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }

        .sig-modal .sig-subtitle {
            margin: 0 0 16px;
            font-size: 11px;
            color: #64748b;
            text-align: center;
        }

        .sig-canvas-head {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 6px;
        }

        .sig-clear-btn {
            border: none;
            background: none;
            color: #2563eb;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            padding: 2px 4px;
        }

        .sig-clear-btn:hover {
            text-decoration: underline;
        }

        .sig-canvas-wrap {
            border: 1.5px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            position: relative;
        }

        .sig-canvas-wrap canvas {
            display: block;
            width: 100%;
            height: 190px;
            cursor: crosshair;
            border-radius: 10px;
        }

        .sig-canvas-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 10px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
            pointer-events: none;
        }

        .sig-error {
            display: none;
            color: #dc2626;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            margin-top: 8px;
        }

        .sig-actions {
            margin: 18px -22px 0;
            border-top: 1px solid #e2e8f0;
        }

        .sig-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            text-align: center;
            padding: 13px 0;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
            cursor: pointer;
            background: #fff;
            color: #334155;
        }

        .sig-row:hover {
            background: #f8fafc;
        }

        .sig-row.sig-row-primary {
            border-top: 1px solid #e2e8f0;
            background: #2563eb;
            color: #fff;
            border-radius: 0 0 14px 14px;
        }

        .sig-row.sig-row-primary:hover {
            background: #1d4ed8;
        }

        #sig_upload_input {
            display: none;
        }

        @media print {
            .sig-modal-overlay {
                display: none !important;
            }
        }

        @media print {
            .print-toolbar {
                display: none;
            }
```

Replace with:

```css
        @media print {
            .print-toolbar {
                display: none;
            }
```

- [ ] **Step 2: Remove the toolbar button and the modal HTML block**

Find:

```html
<div class="print-toolbar">
    <button type="button" id="btn_open_signature" onclick="openSignatureModal()" style="margin-right: 8px;">Add Signature</button>
    <button type="button" onclick="window.print()">Print</button>
</div>

<div class="sig-modal-overlay" id="sig_modal_overlay">
    <div class="sig-modal">
        <button type="button" class="sig-modal-close" onclick="closeSignatureModal()">&times;</button>
        <h3>Applicant Signature</h3>
        <p class="sig-subtitle">This will be embedded into the printed form only &mdash; nothing is saved.</p>

        <div class="sig-canvas-head">
            <button type="button" class="sig-clear-btn" onclick="clearSignatureCanvas()">Clear</button>
        </div>
        <div class="sig-canvas-wrap">
            <canvas id="sig_canvas"></canvas>
            <div class="sig-canvas-placeholder" id="sig_placeholder">You can manually draw your signature here<br>or override with upload</div>
        </div>

        <div class="sig-error" id="sig_error">Please draw or upload a signature before saving.</div>

        <div class="sig-actions">
            <label class="sig-row" for="sig_upload_input">&#128228; Upload Signature</label>
            <input type="file" id="sig_upload_input" accept="image/*" onchange="handleSignatureUpload(event)">

            <div class="sig-row sig-row-primary" onclick="saveSignature()">Save Changes</div>
        </div>
    </div>
</div>

<div class="page">
```

Replace with:

```html
<div class="print-toolbar">
    <button type="button" onclick="window.print()">Print</button>
</div>

<div class="page">
```

- [ ] **Step 3: Remove the trailing script block**

Find (immediately after the attachments `@endforeach` loop, right before the closing `</body>`):

```html
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>
<script>
    let signaturePad = null;
    let sigCanvasInitialized = false;
    let hasSignatureContent = false;

    function initSignatureCanvas() {
        const canvas = document.getElementById('sig_canvas');
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);

        signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,0)' });
        signaturePad.addEventListener('beginStroke', function () {
            hasSignatureContent = true;
            updateSignaturePlaceholder();
        });
        sigCanvasInitialized = true;
    }

    function updateSignaturePlaceholder() {
        document.getElementById('sig_placeholder').style.display =
            (hasSignatureContent || (signaturePad && !signaturePad.isEmpty())) ? 'none' : 'flex';
    }

    function openSignatureModal() {
        document.getElementById('sig_error').style.display = 'none';
        document.getElementById('sig_modal_overlay').classList.add('open');
        if (!sigCanvasInitialized) {
            initSignatureCanvas();
        }
        updateSignaturePlaceholder();
    }

    function closeSignatureModal() {
        document.getElementById('sig_modal_overlay').classList.remove('open');
    }

    function clearSignatureCanvas() {
        if (!signaturePad) return;
        signaturePad.clear();
        hasSignatureContent = false;
        document.getElementById('sig_upload_input').value = '';
        document.getElementById('sig_error').style.display = 'none';
        updateSignaturePlaceholder();
    }

    function handleSignatureUpload(event) {
        const file = event.target.files[0];
        if (!file || !signaturePad) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                const canvas = document.getElementById('sig_canvas');
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const w = canvas.width / ratio;
                const h = canvas.height / ratio;
                const scale = Math.min(w / img.width, h / img.height, 1);
                const drawW = img.width * scale;
                const drawH = img.height * scale;
                const x = (w - drawW) / 2;
                const y = (h - drawH) / 2;

                signaturePad.clear();
                canvas.getContext('2d').drawImage(img, x, y, drawW, drawH);
                hasSignatureContent = true;
                document.getElementById('sig_error').style.display = 'none';
                updateSignaturePlaceholder();
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function saveSignature() {
        const hasSignature = hasSignatureContent || (signaturePad && !signaturePad.isEmpty());
        if (!hasSignature) {
            document.getElementById('sig_error').style.display = 'block';
            return;
        }

        const dataUrl = signaturePad.toDataURL('image/png');
        const $line = document.getElementById('applicant_line');
        let img = $line.querySelector('.signature-img');
        if (!img) {
            img = document.createElement('img');
            img.className = 'signature-img';
            img.alt = 'Signature of Applicant';
            $line.insertBefore(img, $line.firstChild);
        }
        img.src = dataUrl;

        closeSignatureModal();
    }
</script>
</body>
</html>
```

Replace with:

```html
</body>
</html>
```

- [ ] **Step 4: Render the applicant's signature**

Find:

```html
                    <div class="applicant-line" id="applicant_line">
                        <span class="sign-line">(Signature of Applicant)</span>
                    </div>
```

Replace with:

```html
                    <div class="applicant-line" id="applicant_line">
                        @if($applicantSignature)
                            <img src="{{ $applicantSignature }}" alt="Signature of Applicant" class="signature-img">
                        @endif
                        <span class="sign-line">(Signature of Applicant)</span>
                    </div>
```

- [ ] **Step 5: Render the SH's signature (7.B Authorized Officer)**

Find:

```html
                    <div class="bottom-authority" style="margin-top: 22px;">
                        <span class="name-inline">{{ $authorizedOfficerName }}</span>
                        <div class="line"></div>
                        <div class="caption">(Authorized Officer)</div>
                    </div>
```

Replace with:

```html
                    <div class="bottom-authority" style="margin-top: 22px;">
                        @if($shSignature)
                            <img src="{{ $shSignature }}" alt="Signature of Authorized Officer" style="display:block; max-height: 34px; max-width: 65%; margin: 0 auto;">
                        @endif
                        <span class="name-inline">{{ $authorizedOfficerName }}</span>
                        <div class="line"></div>
                        <div class="caption">(Authorized Officer)</div>
                    </div>
```

- [ ] **Step 6: Render the DH's signature (bottom Authorized Official)**

Find:

```html
                    <div class="bottom-authority">
                        <span class="name-inline">{{ $authorizedOfficialName }}</span>
                        <div class="line"></div>
                        <div class="caption">(Authorized Official)</div>
                    </div>
```

Replace with:

```html
                    <div class="bottom-authority">
                        @if($dhSignature)
                            <img src="{{ $dhSignature }}" alt="Signature of Authorized Official" style="display:block; max-height: 34px; max-width: 65%; margin: 0 auto;">
                        @endif
                        <span class="name-inline">{{ $authorizedOfficialName }}</span>
                        <div class="line"></div>
                        <div class="caption">(Authorized Official)</div>
                    </div>
```

- [ ] **Step 7: Verify with a temporary render test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TempLeavePrintSignaturesTest extends TestCase
{
    /** @test */
    public function the_print_view_no_longer_has_the_ephemeral_modal_and_renders_stored_signatures_when_present()
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId(1);

        $leaveAppId = DB::table('hris.leave_applications')->orderBy('id')->value('id');

        // Without any stored signatures: no ephemeral-modal markup, blank lines render fine.
        $response = $this->get('/print_leave?id=' . $leaveAppId);
        $response->assertOk();
        $response->assertDontSee('sig_modal_overlay', false);
        $response->assertDontSee('signature_pad@4', false);
        $response->assertDontSee('Add Signature', false);
        $response->assertSee('(Signature of Applicant)', false);
    }
}
```

Save to `tests/Feature/TempLeavePrintSignaturesTest.php`, run:
Run: `php artisan view:clear && php artisan test tests/Feature/TempLeavePrintSignaturesTest.php`
Expected: `Tests: 1 passed`.
Then delete it: `rm tests/Feature/TempLeavePrintSignaturesTest.php`

- [ ] **Step 8: Commit**

```bash
git add resources/views/print/leave_form.blade.php
git commit -m "feat: render stored assignatory signatures on the leave print form

Supersedes the ephemeral print-page signature modal (8f9d8d2, e0e4920)
now that signatures are captured and persisted upstream at filing,
editing, and SH/DH approval time."
```

---

### Task 10: Final regression check

**Files:** none (verification only)

- [ ] **Step 1: Run the full leave-related test suite**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php tests/Feature/LeaveApplicationSignatureTest.php`
Expected: all tests pass (9 + 7 = 16 tests).

- [ ] **Step 2: Confirm no stray temp test files remain**

Run: `git status --short`
Expected: no untracked `Temp*Test.php` files under `tests/Feature/`, and no unexpected modified files.

- [ ] **Step 3: Manual smoke test**

File a leave application (draw a signature in the modal), approve it as SH (draw a different signature), approve as DH (draw a third signature), then print the application and visually confirm all three signatures appear in their correct slots — applicant's above "(Signature of Applicant)", SH's above "(Authorized Officer)" in 7.B, DH's above "(Authorized Official)" at the bottom — and that the print-page toolbar no longer has an "Add Signature" button.
