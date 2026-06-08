# Overtime Multi-Level Approval + CTO Credits Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add SH→DH→HR approval chain to overtime filings, display computed Overtime Hours in both views, and award CTO leave credits when all three levels approve.

**Architecture:** A new `overtime_request_approvals` table (mirroring `leave_application_approvals`) stores the three-step chain. `OvertimeServices` is updated to insert the chain on filing, evaluate per-role queues, step through approvals, and award CTO credits on final HR approval using the `minute_conversions` lookup. Both Blade views gain an Overtime Hours computed column and the employee view gets a richer status badge showing the current chain step.

**Tech Stack:** Laravel 8, MySQL (cross-database: `hris` + `tdh_user`), Blade + vanilla JS (moment.js already available), PHPUnit with DatabaseTransactions.

---

## File Map

| Action | File |
|---|---|
| Create | `database/migrations/2026_06_08_000001_create_overtime_request_approvals_table.php` |
| Modify | `app/Services/OvertimeServices.php` |
| Modify | `app/Http/Controllers/OvertimeController.php` |
| Modify | `resources/views/overtime_file.blade.php` |
| Modify | `resources/views/overtime_approval.blade.php` |
| Create | `tests/Feature/OvertimeApprovalTest.php` |

---

## Task 1: Migration — create overtime_request_approvals

**Files:**
- Create: `database/migrations/2026_06_08_000001_create_overtime_request_approvals_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('overtime_request_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('overtime_request_id');
            $table->integer('approver_id')->nullable();
            $table->enum('role', ['SH', 'DH', 'HR']);
            $table->unsignedTinyInteger('order_no');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('overtime_request_id');
            $table->index(['overtime_request_id', 'role']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('overtime_request_approvals');
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected output contains: `2026_06_08_000001_create_overtime_request_approvals_table ......... running`

- [ ] **Step 3: Verify table exists**

```bash
php artisan tinker --execute="echo DB::select('SHOW TABLES LIKE \'overtime_request_approvals\'')[0]?->{'Tables_in_hris (overtime_request_approvals)'} ?? 'NOT FOUND';"
```

Expected: `overtime_request_approvals`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_08_000001_create_overtime_request_approvals_table.php
git commit -m "feat: add overtime_request_approvals migration"
```

---

## Task 2: OvertimeServices — resolveOvertimeApprovers() + updated fileForSelf()

**Files:**
- Modify: `app/Services/OvertimeServices.php`
- Create: `tests/Feature/OvertimeApprovalTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/OvertimeApprovalTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OvertimeApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9001;
    private int $shUserId   = 9002;
    private int $dhUserId   = 9003;
    private int $hrUserId   = 15;
    private int $sectionId  = 801;
    private int $divisionId = 901;

    protected function setUp(): void
    {
        parent::setUp();

        // Section head
        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'SH',   'lname' => 'User',   'section' => $this->sectionId,  'division' => $this->divisionId, 'email' => 'sh@test.com',   'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'DH',   'lname' => 'User',   'section' => $this->sectionId,  'division' => $this->divisionId, 'email' => 'dh@test.com',   'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp',  'lname' => 'Test',   'section' => $this->sectionId,  'division' => $this->divisionId, 'email' => 'emp@test.com',  'password' => 'x'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'name' => 'TestSection', 'head' => $this->shUserId],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'name' => 'TestDivision', 'head' => $this->dhUserId],
        ]);
    }

    private function actAsEmployee(): void
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object)[
            'id'       => $this->employeeId,
            'section'  => $this->sectionId,
            'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
    }

    /** @test */
    public function filing_creates_three_approval_rows_with_correct_approver_ids()
    {
        $this->actAsEmployee();
        $service = app(\App\Services\OvertimeServices::class);

        $service->fileForSelf('2026-07-01', '17:00', '19:00', 'Test OT');

        $requestId = DB::table('hris.overtime_requests')
            ->where('userid', $this->employeeId)
            ->value('id');

        $approvals = DB::table('hris.overtime_request_approvals')
            ->where('overtime_request_id', $requestId)
            ->orderBy('order_no')
            ->get(['role', 'order_no', 'approver_id', 'status']);

        $this->assertCount(3, $approvals);

        $sh = $approvals->firstWhere('role', 'SH');
        $dh = $approvals->firstWhere('role', 'DH');
        $hr = $approvals->firstWhere('role', 'HR');

        $this->assertEquals($this->shUserId, $sh->approver_id);
        $this->assertEquals(1, $sh->order_no);
        $this->assertEquals('pending', $sh->status);

        $this->assertEquals($this->dhUserId, $dh->approver_id);
        $this->assertEquals(2, $dh->order_no);
        $this->assertEquals('pending', $dh->status);

        $this->assertEquals($this->hrUserId, $hr->approver_id);
        $this->assertEquals(3, $hr->order_no);
        $this->assertEquals('pending', $hr->status);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter filing_creates_three_approval_rows_with_correct_approver_ids
```

Expected: FAIL — `Call to undefined method App\Services\OvertimeServices::resolveOvertimeApprovers()` or similar.

- [ ] **Step 3: Add resolveOvertimeApprovers() and update fileForSelf() in OvertimeServices**

In `app/Services/OvertimeServices.php`, add the private helper and rewrite `fileForSelf()`:

```php
private function resolveOvertimeApprovers(int $userId): array
{
    $employee = DB::table('tdh_user.users')->where('id', $userId)->first(['section', 'division']);

    $shId = null;
    $dhId = null;

    if ($employee && $employee->section) {
        $shId = DB::table('tdh_user.section')
            ->where('id', $employee->section)
            ->value('head');
    }

    if ($employee && $employee->division) {
        $dhId = DB::table('tdh_user.division')
            ->where('id', $employee->division)
            ->value('head');
    }

    return [
        ['role' => 'SH', 'order_no' => 1, 'approver_id' => $shId ? (int) $shId : null],
        ['role' => 'DH', 'order_no' => 2, 'approver_id' => $dhId ? (int) $dhId : null],
        ['role' => 'HR', 'order_no' => 3, 'approver_id' => 15],
    ];
}

public function fileForSelf($otDate, $timeFrom, $timeTo, $reason = null)
{
    $exists = DB::table('hris.overtime_requests')
        ->where('userid', Auth::id())
        ->where('ot_date', $otDate)
        ->where('time_from', $timeFrom)
        ->where('time_to', $timeTo)
        ->exists();

    if ($exists) {
        throw ValidationException::withMessages([
            'ot_date' => 'You already filed an overtime request with the same date and time range.',
        ]);
    }

    DB::transaction(function () use ($otDate, $timeFrom, $timeTo, $reason) {
        $requestId = DB::table('hris.overtime_requests')->insertGetId([
            'userid'     => Auth::id(),
            'ot_date'    => $otDate,
            'time_from'  => $timeFrom,
            'time_to'    => $timeTo,
            'reason'     => $reason,
            'status'     => 'pending',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $approvers = $this->resolveOvertimeApprovers(Auth::id());
        $now = Carbon::now();

        foreach ($approvers as $approver) {
            $autoApproved = ($approver['approver_id'] === null);
            DB::table('hris.overtime_request_approvals')->insert([
                'overtime_request_id' => $requestId,
                'approver_id'         => $approver['approver_id'],
                'role'                => $approver['role'],
                'order_no'            => $approver['order_no'],
                'status'              => $autoApproved ? 'approved' : 'pending',
                'approved_at'         => $autoApproved ? $now : null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }
    });
}
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter filing_creates_three_approval_rows_with_correct_approver_ids
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/OvertimeServices.php tests/Feature/OvertimeApprovalTest.php
git commit -m "feat: insert approval chain rows on overtime filing"
```

---

## Task 3: OvertimeServices — awardCtoCredits()

**Files:**
- Modify: `app/Services/OvertimeServices.php`
- Modify: `tests/Feature/OvertimeApprovalTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/OvertimeApprovalTest.php`:

```php
/** @test */
public function award_cto_credits_calculates_correctly_for_90_minutes()
{
    // 90 min OT = 1 full hour (0.125) + 30 remaining (0.063) = 0.188 credits
    $service = app(\App\Services\OvertimeServices::class);

    // Use reflection to call private method
    $method = new \ReflectionMethod($service, 'awardCtoCredits');
    $method->setAccessible(true);
    $method->invoke($service, $this->employeeId, '17:00:00', '18:30:00');

    $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
    $credit = DB::table('hris.leave_credits')
        ->where('user_id', $this->employeeId)
        ->where('leave_id', $ctoLeaveId)
        ->first();

    $this->assertNotNull($credit);
    $this->assertEquals(0.188, round((float) $credit->based_credit, 3));
}

/** @test */
public function award_cto_credits_increments_existing_balance()
{
    $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');

    DB::table('hris.leave_credits')->insert([
        'user_id'      => $this->employeeId,
        'leave_id'     => $ctoLeaveId,
        'based_credit' => 1.000,
        'used_credit'  => 0,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $service = app(\App\Services\OvertimeServices::class);
    $method = new \ReflectionMethod($service, 'awardCtoCredits');
    $method->setAccessible(true);
    // 60 min = 0.125 credits
    $method->invoke($service, $this->employeeId, '17:00:00', '18:00:00');

    $credit = DB::table('hris.leave_credits')
        ->where('user_id', $this->employeeId)
        ->where('leave_id', $ctoLeaveId)
        ->first();

    $this->assertEquals(1.125, round((float) $credit->based_credit, 3));
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter award_cto
```

Expected: FAIL — method `awardCtoCredits` not found.

- [ ] **Step 3: Add awardCtoCredits() to OvertimeServices**

Add this private method to `app/Services/OvertimeServices.php`:

```php
private function awardCtoCredits(int $userId, string $timeFrom, string $timeTo): void
{
    $from = Carbon::createFromFormat('H:i:s', $timeFrom);
    $to   = Carbon::createFromFormat('H:i:s', $timeTo);
    $totalMinutes = (int) $from->diffInMinutes($to);

    if ($totalMinutes <= 0) return;

    $minuteConversions = DB::table('hris.minute_conversions')
        ->pluck('points_deduction', 'minute');

    $fullHours = intdiv($totalMinutes, 60);
    $remaining = $totalMinutes % 60;

    $credits = ((float) ($minuteConversions[60] ?? 0)) * $fullHours
             + ($remaining > 0 ? (float) ($minuteConversions[$remaining] ?? 0) : 0);

    if ($credits <= 0) return;

    $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
    if (!$ctoLeaveId) return;

    $existing = DB::table('hris.leave_credits')
        ->where('user_id', $userId)
        ->where('leave_id', $ctoLeaveId)
        ->first(['id', 'based_credit']);

    if ($existing) {
        DB::table('hris.leave_credits')
            ->where('id', $existing->id)
            ->update([
                'based_credit' => (float) $existing->based_credit + $credits,
                'updated_at'   => Carbon::now(),
            ]);
    } else {
        DB::table('hris.leave_credits')->insert([
            'user_id'      => $userId,
            'leave_id'     => $ctoLeaveId,
            'based_credit' => $credits,
            'used_credit'  => 0,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter award_cto
```

Expected: 2 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/OvertimeServices.php tests/Feature/OvertimeApprovalTest.php
git commit -m "feat: add awardCtoCredits method to OvertimeServices"
```

---

## Task 4: OvertimeServices — rewrite reviewFiling() for multi-level

**Files:**
- Modify: `app/Services/OvertimeServices.php`
- Modify: `tests/Feature/OvertimeApprovalTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/OvertimeApprovalTest.php`:

```php
private function seedFiling(string $timeFrom = '17:00:00', string $timeTo = '19:00:00'): int
{
    $requestId = DB::table('hris.overtime_requests')->insertGetId([
        'userid'     => $this->employeeId,
        'ot_date'    => '2026-07-01',
        'time_from'  => $timeFrom,
        'time_to'    => $timeTo,
        'reason'     => 'Test',
        'status'     => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('hris.overtime_request_approvals')->insert([
        ['overtime_request_id' => $requestId, 'approver_id' => $this->shUserId, 'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ['overtime_request_id' => $requestId, 'approver_id' => $this->dhUserId, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ['overtime_request_id' => $requestId, 'approver_id' => $this->hrUserId, 'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
    ]);

    return $requestId;
}

private function actAs(int $userId, bool $isAdmin = false): void
{
    Auth::shouldReceive('id')->andReturn($userId);
    Auth::shouldReceive('check')->andReturn(true);
    session(['access_rights' => $isAdmin ? 'admin' : 'employee']);
}

/** @test */
public function sh_approval_marks_sh_row_approved_and_filing_stays_pending()
{
    $requestId = $this->seedFiling();
    $this->actAs($this->shUserId);

    $service = app(\App\Services\OvertimeServices::class);
    $service->reviewFiling($requestId, 'approved', 'Looks good');

    $shRow = DB::table('hris.overtime_request_approvals')
        ->where('overtime_request_id', $requestId)->where('role', 'SH')->first();
    $this->assertEquals('approved', $shRow->status);
    $this->assertNotNull($shRow->approved_at);

    $filing = DB::table('hris.overtime_requests')->where('id', $requestId)->first();
    $this->assertEquals('pending', $filing->status);
}

/** @test */
public function rejection_at_sh_level_closes_filing_immediately()
{
    $requestId = $this->seedFiling();
    $this->actAs($this->shUserId);

    $service = app(\App\Services\OvertimeServices::class);
    $service->reviewFiling($requestId, 'rejected', 'Not justified');

    $shRow = DB::table('hris.overtime_request_approvals')
        ->where('overtime_request_id', $requestId)->where('role', 'SH')->first();
    $this->assertEquals('rejected', $shRow->status);

    $filing = DB::table('hris.overtime_requests')->where('id', $requestId)->first();
    $this->assertEquals('rejected', $filing->status);

    $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
    $credits = DB::table('hris.leave_credits')
        ->where('user_id', $this->employeeId)->where('leave_id', $ctoLeaveId)->first();
    $this->assertNull($credits);
}

/** @test */
public function full_chain_approval_awards_cto_credits_and_closes_filing()
{
    // 60 min OT = 0.125 credits
    $requestId = $this->seedFiling('17:00:00', '18:00:00');
    $service = app(\App\Services\OvertimeServices::class);

    // SH approves
    $this->actAs($this->shUserId);
    $service->reviewFiling($requestId, 'approved');

    // DH approves
    $this->actAs($this->dhUserId);
    $service->reviewFiling($requestId, 'approved');

    // HR approves
    $this->actAs($this->hrUserId);
    $service->reviewFiling($requestId, 'approved');

    $filing = DB::table('hris.overtime_requests')->where('id', $requestId)->first();
    $this->assertEquals('approved', $filing->status);
    $this->assertEquals($this->hrUserId, (int) $filing->reviewed_by);

    $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
    $credit = DB::table('hris.leave_credits')
        ->where('user_id', $this->employeeId)->where('leave_id', $ctoLeaveId)->first();
    $this->assertNotNull($credit);
    $this->assertEquals(0.125, round((float) $credit->based_credit, 3));
}

/** @test */
public function dh_cannot_approve_before_sh_approves()
{
    $requestId = $this->seedFiling();
    $this->actAs($this->dhUserId);

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    $service = app(\App\Services\OvertimeServices::class);
    $service->reviewFiling($requestId, 'approved');
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter "sh_approval|rejection_at_sh|full_chain|dh_cannot"
```

Expected: All FAIL.

- [ ] **Step 3: Rewrite reviewFiling() in OvertimeServices**

Replace the existing `reviewFiling()` method entirely:

```php
public function reviewFiling($filingId, $status, $remarks = null)
{
    $userId  = Auth::id();
    $isAdmin = $this->isAdmin();

    DB::transaction(function () use ($filingId, $status, $remarks, $userId, $isAdmin) {
        $filing = DB::table('hris.overtime_requests')
            ->where('id', $filingId)
            ->where('status', 'pending')
            ->first(['id', 'status', 'userid', 'time_from', 'time_to']);

        if (!$filing) {
            throw ValidationException::withMessages([
                'id' => 'Overtime filing not found or is not pending.',
            ]);
        }

        // Find the current active step for this reviewer.
        // Active = pending with no earlier pending step.
        $approvalQuery = DB::table('hris.overtime_request_approvals as ora')
            ->where('ora.overtime_request_id', $filingId)
            ->where('ora.status', 'pending')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('hris.overtime_request_approvals as prev')
                    ->whereColumn('prev.overtime_request_id', 'ora.overtime_request_id')
                    ->whereRaw('prev.order_no < ora.order_no')
                    ->where('prev.status', 'pending');
            });

        if (!$isAdmin) {
            $approvalQuery->where('ora.approver_id', $userId);
        }

        $approval = $approvalQuery->first(['ora.id', 'ora.role', 'ora.order_no']);

        if (!$approval) {
            throw ValidationException::withMessages([
                'id' => 'No active approval step found for you on this filing.',
            ]);
        }

        $now = Carbon::now();

        DB::table('hris.overtime_request_approvals')
            ->where('id', $approval->id)
            ->update([
                'status'      => $status,
                'remarks'     => $remarks,
                'approved_at' => $now,
                'updated_at'  => $now,
            ]);

        if ($status === 'rejected') {
            DB::table('hris.overtime_requests')
                ->where('id', $filingId)
                ->update([
                    'status'      => 'rejected',
                    'reviewed_by' => $userId,
                    'reviewed_at' => $now,
                    'updated_at'  => $now,
                ]);
            return;
        }

        // Approved — check if all steps are now done
        $anyStillPending = DB::table('hris.overtime_request_approvals')
            ->where('overtime_request_id', $filingId)
            ->where('status', 'pending')
            ->exists();

        if (!$anyStillPending) {
            $this->awardCtoCredits((int) $filing->userid, $filing->time_from, $filing->time_to);

            DB::table('hris.overtime_requests')
                ->where('id', $filingId)
                ->update([
                    'status'      => 'approved',
                    'reviewed_by' => $userId,
                    'reviewed_at' => $now,
                    'updated_at'  => $now,
                ]);
        }
    });
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter "sh_approval|rejection_at_sh|full_chain|dh_cannot"
```

Expected: 4 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/OvertimeServices.php tests/Feature/OvertimeApprovalTest.php
git commit -m "feat: rewrite reviewFiling for multi-level approval chain"
```

---

## Task 5: OvertimeServices — update getMyFilings() with pending_step

**Files:**
- Modify: `app/Services/OvertimeServices.php`
- Modify: `tests/Feature/OvertimeApprovalTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/OvertimeApprovalTest.php`:

```php
/** @test */
public function get_my_filings_returns_pending_step_field()
{
    $requestId = $this->seedFiling();
    $this->actAs($this->employeeId);

    $service = app(\App\Services\OvertimeServices::class);
    $filings = $service->getMyFilings();

    $filing = $filings->firstWhere('id', $requestId);
    $this->assertNotNull($filing);
    $this->assertEquals('SH', $filing->pending_step);
}

/** @test */
public function get_my_filings_pending_step_is_null_when_approved()
{
    $requestId = $this->seedFiling();

    // Mark all steps approved
    DB::table('hris.overtime_request_approvals')
        ->where('overtime_request_id', $requestId)
        ->update(['status' => 'approved']);
    DB::table('hris.overtime_requests')
        ->where('id', $requestId)
        ->update(['status' => 'approved']);

    $this->actAs($this->employeeId);

    $service = app(\App\Services\OvertimeServices::class);
    $filings = $service->getMyFilings();

    $filing = $filings->firstWhere('id', $requestId);
    $this->assertNull($filing->pending_step);
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter get_my_filings
```

Expected: FAIL — `pending_step` property missing.

- [ ] **Step 3: Update getMyFilings() in OvertimeServices**

Replace the existing `getMyFilings()` method:

```php
public function getMyFilings()
{
    $filings = DB::table('hris.overtime_requests as ot')
        ->where('ot.userid', Auth::id())
        ->orderBy('ot.created_at', 'desc')
        ->get([
            'ot.id',
            'ot.ot_date',
            'ot.time_from',
            'ot.time_to',
            'ot.reason',
            'ot.status',
            'ot.remarks',
            'ot.reviewed_at',
        ]);

    if ($filings->isEmpty()) return $filings;

    $filingIds = $filings->pluck('id')->toArray();

    $pendingSteps = DB::table('hris.overtime_request_approvals')
        ->whereIn('overtime_request_id', $filingIds)
        ->where('status', 'pending')
        ->orderBy('order_no')
        ->get(['overtime_request_id', 'role'])
        ->groupBy('overtime_request_id')
        ->map(fn($rows) => $rows->first()->role);

    return $filings->map(function ($filing) use ($pendingSteps) {
        $filing->pending_step = $pendingSteps[$filing->id] ?? null;
        return $filing;
    });
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter get_my_filings
```

Expected: 2 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/OvertimeServices.php tests/Feature/OvertimeApprovalTest.php
git commit -m "feat: include pending_step in getMyFilings response"
```

---

## Task 6: OvertimeServices — update getFilingsForReview() for role-based filtering

**Files:**
- Modify: `app/Services/OvertimeServices.php`
- Modify: `tests/Feature/OvertimeApprovalTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/OvertimeApprovalTest.php`:

```php
/** @test */
public function sh_only_sees_filings_at_their_step()
{
    $requestId = $this->seedFiling();
    $this->actAs($this->shUserId);

    $service = app(\App\Services\OvertimeServices::class);
    $results = $service->getFilingsForReview();

    $found = collect($results)->firstWhere('id', $requestId);
    $this->assertNotNull($found);
    $this->assertEquals('SH', $found->current_step);
}

/** @test */
public function dh_does_not_see_filing_before_sh_approves()
{
    $requestId = $this->seedFiling();
    $this->actAs($this->dhUserId);

    $service = app(\App\Services\OvertimeServices::class);
    $results = $service->getFilingsForReview();

    $found = collect($results)->firstWhere('id', $requestId);
    $this->assertNull($found);
}

/** @test */
public function dh_sees_filing_after_sh_approves()
{
    $requestId = $this->seedFiling();

    // SH approves first
    DB::table('hris.overtime_request_approvals')
        ->where('overtime_request_id', $requestId)->where('role', 'SH')
        ->update(['status' => 'approved', 'approved_at' => now()]);

    $this->actAs($this->dhUserId);

    $service = app(\App\Services\OvertimeServices::class);
    $results = $service->getFilingsForReview();

    $found = collect($results)->firstWhere('id', $requestId);
    $this->assertNotNull($found);
    $this->assertEquals('DH', $found->current_step);
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter "sh_only_sees|dh_does_not|dh_sees_filing"
```

Expected: FAIL — `current_step` property missing or wrong results.

- [ ] **Step 3: Rewrite getFilingsForReview() in OvertimeServices**

Replace the existing `getFilingsForReview()` method entirely:

```php
public function getFilingsForReview()
{
    $userId  = Auth::id();
    $isAdmin = $this->isAdmin();

    $employeeNameExpr = DB::raw("CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(LEFT(u.mname,1), ''), CASE WHEN u.mname IS NULL OR u.mname = '' THEN '' ELSE '. ' END, COALESCE(u.lname, ''), CASE WHEN u.suffix IS NULL OR u.suffix = '' THEN '' ELSE CONCAT(' ', u.suffix) END) as employee_name");

    $columns = [
        'ot.id',
        'ot.userid',
        $employeeNameExpr,
        'ot.ot_date',
        'ot.time_from',
        'ot.time_to',
        'ot.reason',
        'ot.status',
        'ot.remarks',
        'ot.reviewed_at',
        'ora.role as current_step',
    ];

    $query = DB::table('hris.overtime_requests as ot')
        ->join('tdh_user.users as u', 'u.id', '=', 'ot.userid')
        ->join('hris.overtime_request_approvals as ora', function ($join) {
            $join->on('ora.overtime_request_id', '=', 'ot.id')
                 ->where('ora.status', 'pending');
        })
        ->where('ot.status', 'pending')
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('hris.overtime_request_approvals as prev')
                ->whereColumn('prev.overtime_request_id', 'ot.id')
                ->whereRaw('prev.order_no < ora.order_no')
                ->where('prev.status', 'pending');
        })
        ->orderBy('ot.created_at', 'desc');

    if (!$isAdmin) {
        $query->where('ora.approver_id', $userId);
    }

    return $query->get($columns);
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php --filter "sh_only_sees|dh_does_not|dh_sees_filing"
```

Expected: 3 tests PASS

- [ ] **Step 5: Run the full test suite to make sure nothing broke**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php
```

Expected: All tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/OvertimeServices.php tests/Feature/OvertimeApprovalTest.php
git commit -m "feat: filter getFilingsForReview by caller's approval step"
```

---

## Task 7: OvertimeServices — update deleteMyFiling() to cascade-delete approvals

**Files:**
- Modify: `app/Services/OvertimeServices.php`

- [ ] **Step 1: Update deleteMyFiling() in OvertimeServices**

Replace the existing `deleteMyFiling()` method:

```php
public function deleteMyFiling($filingId)
{
    $filing = DB::table('hris.overtime_requests')
        ->where('id', $filingId)
        ->where('userid', Auth::id())
        ->first(['id', 'status']);

    if (!$filing) {
        throw ValidationException::withMessages([
            'id' => 'Overtime filing record not found.',
        ]);
    }

    $status = strtolower((string) $filing->status);
    if (!in_array($status, ['pending', 'rejected'], true)) {
        throw ValidationException::withMessages([
            'id' => 'Only pending or rejected overtime filings can be deleted.',
        ]);
    }

    DB::transaction(function () use ($filingId) {
        DB::table('hris.overtime_request_approvals')
            ->where('overtime_request_id', $filingId)
            ->delete();

        DB::table('hris.overtime_requests')
            ->where('id', $filingId)
            ->delete();
    });
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/OvertimeServices.php
git commit -m "fix: cascade delete approval rows when overtime filing is deleted"
```

---

## Task 8: OvertimeController — extend canReviewOvertimeFilings() for HR

**Files:**
- Modify: `app/Http/Controllers/OvertimeController.php`

- [ ] **Step 1: Update canReviewOvertimeFilings() in OvertimeController**

Replace the existing private method:

```php
private function canReviewOvertimeFilings()
{
    return in_array(session('access_rights'), ['admin'], true)
        || (bool) session('is_section_head')
        || (bool) session('is_division_head')
        || Auth::id() == 15;
}
```

Also add `use Illuminate\Support\Facades\Auth;` at the top of the controller if not already imported. Check with:

```bash
grep "use Illuminate\\\\Support\\\\Facades\\\\Auth" app/Http/Controllers/OvertimeController.php
```

If missing, add it under the existing `use` statements.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/OvertimeController.php
git commit -m "feat: allow HR user to access overtime review endpoints"
```

---

## Task 9: overtime_file.blade.php — Overtime Hours column + enriched status badge

**Files:**
- Modify: `resources/views/overtime_file.blade.php`

- [ ] **Step 1: Add OT Hours column header to the table**

In `resources/views/overtime_file.blade.php`, find the `<thead>` block:

```html
<tr>
    <th>Date</th>
    <th>Time Range</th>
    <th>Reason</th>
    <th>Status</th>
    <th>Remarks</th>
    <th>Action</th>
</tr>
```

Replace with:

```html
<tr>
    <th>Date</th>
    <th>Time Range</th>
    <th>OT Hours</th>
    <th>Reason</th>
    <th>Status</th>
    <th>Remarks</th>
    <th>Action</th>
</tr>
```

- [ ] **Step 2: Update the colspan on the empty/loading row**

Change all `colspan="6"` inside `#my_filing_list` to `colspan="7"`:

```html
<tr><td colspan="7" class="ot-empty">Loading...</td></tr>
```

- [ ] **Step 3: Add formatOtHours() and update statusBadge() in the JS section**

Add the `formatOtHours` function before the closing `</script>` tag:

```js
function formatOtHours(timeFrom, timeTo) {
    if (!timeFrom || !timeTo) return '-';
    const totalMins = moment(timeTo, 'HH:mm:ss').diff(moment(timeFrom, 'HH:mm:ss'), 'minutes');
    if (totalMins <= 0) return '-';
    const h = Math.floor(totalMins / 60);
    const m = totalMins % 60;
    if (h > 0 && m > 0) return `${h}h ${m}m`;
    if (h > 0) return `${h}h`;
    return `${m}m`;
}
```

Replace the existing `statusBadge` function with:

```js
function statusBadge(status, pendingStep) {
    const s = (status || 'pending').toLowerCase();
    let cls = 'secondary';
    let label = s;
    if (s === 'approved') { cls = 'success'; }
    if (s === 'rejected') { cls = 'danger'; }
    if (s === 'pending') {
        cls = 'warning text-dark';
        label = pendingStep ? `Pending (${pendingStep})` : 'Pending';
    }
    return `<span class="badge bg-${cls} text-uppercase">${escapeHtml(label)}</span>`;
}
```

- [ ] **Step 4: Update renderFilings() to use the new column and updated statusBadge**

Find the `pageItems.forEach` block inside `renderFilings()`. Replace the `$list.append(...)` template literal:

```js
pageItems.forEach(it => {
    $list.append(`
        <tr>
            <td>${formatDate(it.ot_date)}</td>
            <td>${formatTimeRange(it.time_from, it.time_to)}</td>
            <td>${formatOtHours(it.time_from, it.time_to)}</td>
            <td>${escapeHtml(it.reason || '-')}</td>
            <td>${statusBadge(it.status, it.pending_step || null)}</td>
            <td>${escapeHtml(it.remarks || '-')}</td>
            <td>${deleteAction(it)}</td>
        </tr>
    `);
});
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/overtime_file.blade.php
git commit -m "feat: add OT Hours column and chain-aware status badge to employee overtime view"
```

---

## Task 10: overtime_approval.blade.php — Overtime Hours column + Level column for admin

**Files:**
- Modify: `resources/views/overtime_approval.blade.php`

- [ ] **Step 1: Add OT Hours and Level column headers**

Find the `<thead>` block in `overtime_approval.blade.php`:

```html
<tr>
    <th>Employee</th>
    <th>Date</th>
    <th>Time Range</th>
    <th>Reason</th>
    <th>Status</th>
    <th>Remarks</th>
    <th>Action</th>
</tr>
```

Replace with:

```html
<tr>
    <th>Employee</th>
    <th>Date</th>
    <th>Time Range</th>
    <th>OT Hours</th>
    <th>Reason</th>
    <th>Level</th>
    <th>Action</th>
</tr>
```

- [ ] **Step 2: Update the colspan on the empty/loading row**

Change `colspan="7"` on the `#review_list` loading row — it's already 7 so verify it matches the new column count (still 7).

- [ ] **Step 3: Add formatOtHours() to the JS section**

Add inside the `<script>` block before the closing `</script>`:

```js
function formatOtHours(timeFrom, timeTo) {
    if (!timeFrom || !timeTo) return '-';
    const totalMins = moment(timeTo, 'HH:mm:ss').diff(moment(timeFrom, 'HH:mm:ss'), 'minutes');
    if (totalMins <= 0) return '-';
    const h = Math.floor(totalMins / 60);
    const m = totalMins % 60;
    if (h > 0 && m > 0) return `${h}h ${m}m`;
    if (h > 0) return `${h}h`;
    return `${m}m`;
}
```

- [ ] **Step 4: Update renderReviewRows() to use the new columns**

Find the `pageItems.forEach` block inside `renderReviewRows()`. Replace the `$list.append(...)` template literal:

```js
pageItems.forEach(it => {
    const canReview = String(it.status || '').toLowerCase() === 'pending';
    const levelBadge = it.current_step
        ? `<span class="badge bg-secondary text-uppercase">${escapeHtml(it.current_step)}</span>`
        : '-';
    $list.append(`
        <tr>
            <td>${escapeHtml((it.employee_name || '').replace(/\s+/g, ' ').trim())}</td>
            <td>${formatDate(it.ot_date)}</td>
            <td>${formatTimeRange(it.time_from, it.time_to)}</td>
            <td>${formatOtHours(it.time_from, it.time_to)}</td>
            <td>${escapeHtml(it.reason || '-')}</td>
            <td>${levelBadge}</td>
            <td>
                ${canReview ? `
                    <button type="button" class="btn btn-action btn-edit me-1 mb-1" onclick="reviewFiling(${it.id}, 'approve')" title="Approve"><i class="bi bi-check2"></i></button>
                    <button type="button" class="btn btn-action btn-delete mb-1" onclick="reviewFiling(${it.id}, 'reject')" title="Reject"><i class="bi bi-x"></i></button>
                ` : '-'}
            </td>
        </tr>
    `);
});
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/overtime_approval.blade.php
git commit -m "feat: add OT Hours and Level columns to overtime approval view"
```

---

## Final Verification

- [ ] **Run full test suite**

```bash
php artisan test tests/Feature/OvertimeApprovalTest.php
```

Expected: All tests PASS

- [ ] **Manual smoke test — employee files an overtime request**

Navigate to `/overtime/file`. Fill in Date, Time From, Time To, Reason and click File. Confirm:
- Toast shows "Overtime request submitted successfully."
- The new row appears in the table with an **OT Hours** column (e.g., "2h") and **Status** showing "Pending (SH)".

- [ ] **Manual smoke test — SH approves**

Log in as the section head user. Navigate to `/overtime/approval`. Confirm:
- The filing appears with OT Hours and Level = "SH".
- Approve it. Confirm the row disappears from the SH queue.
- Log back in as the employee — Status now shows "Pending (DH)".

- [ ] **Manual smoke test — DH approves**

Log in as the DH user. Navigate to `/overtime/approval`. Confirm the filing now appears at Level "DH". Approve.

- [ ] **Manual smoke test — HR approves and credits are awarded**

Log in as HR (user 15). Navigate to `/overtime/approval`. Confirm the filing appears at Level "HR". Approve.
- Log back in as the employee. Status shows "Approved".
- Verify CTO leave credits were added: check the leave balance for CTO in the leave module.

- [ ] **Manual smoke test — rejection stops the chain**

File a second overtime request. Have SH reject it. Confirm:
- Employee's status shows "Rejected".
- DH and HR queues do NOT show the filing.
