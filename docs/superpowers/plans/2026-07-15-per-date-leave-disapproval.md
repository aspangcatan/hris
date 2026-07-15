# Per-Date Leave Disapproval Flagging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a Supervisor (SH) suggest specific dates within a leave application for disapproval while still approving the application overall, let the Division Head (DH) give the final per-date verdict (uphold, override, or flag a new date), and restore leave credits for whichever dates end up disapproved — without changing HR's stage or the application-level approve/reject status.

**Architecture:** Four new columns on `leave_date_applied` track two layers of state: `sh_flagged_disapprove`/`sh_disapproval_remark` (SH's persistent suggestion) and `final_disapproved`/`dh_disapproval_remark` (DH's operative verdict, the only thing that ever touches credits). `LeaveApprovalService::updateApprovalStatus()` gains an optional `$disapprovedDates` parameter and a new private `applyDateDisapprovals()` helper that branches on role: SH just records flags, DH diffs old vs. new `final_disapproved` per date and restores/re-deducts `leave_credits.based_credit` accordingly. `undoApproval()` gains a matching reversal for the DH stage. The approval modal (`modals/leave.blade.php`, shared by both the approver's and the employee's own views) gets a 4th "Disapprove" column in its Dates Applied table — interactive checkboxes for the currently-eligible SH/DH approver, a read-only badge everywhere else.

**Tech Stack:** Laravel 8 / PHP, MySQL (`hris` schema — `leave_date_applied`, `leave_application_approvals`, `leave_applications`, `leave_credits` are all MyISAM in this environment, so tests use `DatabaseTransactions` with explicit manual cleanup, not transactional rollback), Blade + jQuery, PHPUnit.

**Reference spec:** `docs/superpowers/specs/2026-07-15-per-date-leave-disapproval-design.md`

---

### Task 1: Migration — add disapproval flags to `leave_date_applied`

**Files:**
- Create: `database/migrations/2026_07_15_150000_add_disapproval_flags_to_leave_date_applied_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an approver disapprove specific dates within a leave application
     * instead of only the whole application - see
     * docs/superpowers/specs/2026-07-15-per-date-leave-disapproval-design.md.
     *
     * sh_flagged_disapprove/sh_disapproval_remark record the Supervisor's
     * suggestion and are never cleared by DH - they're what a DH "undo"
     * reverts back to. final_disapproved/dh_disapproval_remark are the
     * operative fields: only the Division Head sets them, and they drive
     * both credit restoration and the read-only "Disapproved" badge shown
     * elsewhere in the app.
     */
    public function up()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->boolean('sh_flagged_disapprove')->default(false)->after('credits_deducted');
            $table->string('sh_disapproval_remark', 255)->nullable()->after('sh_flagged_disapprove');
            $table->boolean('final_disapproved')->default(false)->after('sh_disapproval_remark');
            $table->string('dh_disapproval_remark', 255)->nullable()->after('final_disapproved');
        });
    }

    public function down()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->dropColumn(['sh_flagged_disapprove', 'sh_disapproval_remark', 'final_disapproved', 'dh_disapproval_remark']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_07_15_150000_add_disapproval_flags_to_leave_date_applied_table` then `Migrated:` with no errors.

- [ ] **Step 3: Verify the columns exist**

Run:
```bash
php artisan tinker --execute="print_r(Schema::getColumnListing('leave_date_applied'));"
```
Expected: array includes `sh_flagged_disapprove`, `sh_disapproval_remark`, `final_disapproved`, `dh_disapproval_remark`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_15_150000_add_disapproval_flags_to_leave_date_applied_table.php
git commit -m "feat: add per-date disapproval flag columns to leave_date_applied"
```

---

### Task 2: SH suggests, DH finalizes — per-date disapproval and credit adjustment

**Files:**
- Modify: `app/Services/LeaveApprovalService.php:295-370` (`updateApprovalStatus`), add new private method after `restoreCredits()` (currently ending at line 391)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:176-184` (`updateApprovalStatus`)
- Create: `tests/Feature/LeaveDateDisapprovalTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveDateDisapprovalTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9151;
    private int $shUserId   = 9152;
    private int $dhUserId   = 9153;
    private int $sectionId  = 9251;
    private int $divisionId = 9252;
    private int $leaveTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'SH',  'lname' => 'User', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'lda_sh_test',  'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'DH',  'lname' => 'User', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'lda_dh_test',  'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'Test', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'lda_emp_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'LDA Test Division', 'head' => $this->dhUserId, 'code' => 'LDADIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'LDA Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'LDASEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'LDATEST'],
            ['description' => 'LDA Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'LDATEST')->value('id');
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

    /**
     * Seeds a pending leave application for the test employee with 2 dates
     * (1.000 credit each), a starting leave_credits balance of
     * based_credit=5.000/used_credit=3.000, and the standard SH->DH->HR
     * approval chain (SH approver_id=section, DH approver_id=division,
     * HR approver_id=15), all pending.
     *
     * @return array{app_id: int, date_ids: \Illuminate\Support\Collection}
     */
    private function seedApplication(): array
    {
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->employeeId,
            'status' => 'pending',
            'commutation' => 'Not Requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.leave_date_applied')->insert([
            ['leave_application_id' => $appId, 'leave_type_id' => $this->leaveTypeId, 'date_applied' => '2027-01-04', 'hours' => 8, 'status' => 'active', 'credits_deducted' => 1.000],
            ['leave_application_id' => $appId, 'leave_type_id' => $this->leaveTypeId, 'date_applied' => '2027-01-05', 'hours' => 8, 'status' => 'active', 'credits_deducted' => 1.000],
        ]);
        $dateIds = DB::table('hris.leave_date_applied')->where('leave_application_id', $appId)->orderBy('date_applied')->pluck('id');

        DB::table('hris.leave_application_approvals')->insert([
            ['leave_application_id' => $appId, 'approver_id' => $this->sectionId,  'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['leave_application_id' => $appId, 'approver_id' => $this->divisionId, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['leave_application_id' => $appId, 'approver_id' => 15,                'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('hris.leave_credits')->insert([
            'user_id' => $this->employeeId,
            'leave_id' => $this->leaveTypeId,
            'based_credit' => 5.000,
            'used_credit' => 3.000,
            'last_updated_month' => '2027-1',
        ]);

        return ['app_id' => $appId, 'date_ids' => $dateIds];
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

    private function creditBalance(): float
    {
        return (float) DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)
            ->where('leave_id', $this->leaveTypeId)
            ->value('based_credit');
    }

    /** @test */
    public function sh_flagging_a_date_does_not_touch_credits_or_final_status()
    {
        $seed = $this->seedApplication();
        $this->actAsSh();

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Overall fine',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'No coverage that day'],
            ],
        ]);
        $response->assertOk();

        $flagged = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(1, (int) $flagged->sh_flagged_disapprove);
        $this->assertEquals('No coverage that day', $flagged->sh_disapproval_remark);
        $this->assertEquals(0, (int) $flagged->final_disapproved);

        $untouched = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][1])->first();
        $this->assertEquals(0, (int) $untouched->sh_flagged_disapprove);

        $shRow = DB::table('hris.leave_application_approvals')->where('leave_application_id', $seed['app_id'])->where('role', 'SH')->first();
        $this->assertEquals('approved', $shRow->status);

        $this->assertEqualsWithDelta(5.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function dh_upholding_an_sh_flagged_date_restores_its_credits()
    {
        $seed = $this->seedApplication();

        // SH flags date[0] first.
        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);
        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Agreed',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Confirmed no coverage'],
            ],
        ]);
        $response->assertOk();

        $final = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(1, (int) $final->final_disapproved);
        $this->assertEquals('Confirmed no coverage', $final->dh_disapproval_remark);

        // 5.000 + 1.000 restored credit for the disapproved date.
        $this->assertEqualsWithDelta(6.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function dh_overriding_an_sh_flagged_date_back_to_approved_does_not_restore_credits()
    {
        $seed = $this->seedApplication();

        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);
        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        // DH submits with the date NOT in disapproved_dates - overriding SH's flag.
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Coverage was actually fine',
            'disapproved_dates' => [],
        ]);
        $response->assertOk();

        $final = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(0, (int) $final->final_disapproved);

        $this->assertEqualsWithDelta(5.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function dh_can_flag_a_new_date_sh_never_flagged()
    {
        $seed = $this->seedApplication();

        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Caught this one myself',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][1], 'remark' => 'DH caught a conflict'],
            ],
        ]);
        $response->assertOk();

        $final = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][1])->first();
        $this->assertEquals(1, (int) $final->final_disapproved);
        $this->assertEquals(0, (int) $final->sh_flagged_disapprove);

        $this->assertEqualsWithDelta(6.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function sh_rejecting_the_whole_application_restores_all_credits_regardless_of_flags()
    {
        $seed = $this->seedApplication();
        $this->actAsSh();

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Rejected',
            'remarks' => 'Not approved at all',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Irrelevant on reject'],
            ],
        ]);
        $response->assertOk();

        // Whole-application rejection restores every date's credits via the
        // existing restoreCredits() path - both dates, 1.000 each = 2.000.
        $this->assertEqualsWithDelta(7.000, $this->creditBalance(), 0.001);

        $flagged = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(0, (int) $flagged->final_disapproved);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php`
Expected: FAIL on every test — `disapproved_dates` isn't accepted by the controller/service yet, so `sh_flagged_disapprove`/`final_disapproved` never change and credit balances stay at the seeded `5.000`.

- [ ] **Step 3: Extend `updateApprovalStatus()`'s signature and wire in the new helper**

In `app/Services/LeaveApprovalService.php`, find (lines 295-296):

```php
    public function updateApprovalStatus(int $leave_id, ?string $role = null, ?string $remarks = null, ?string $status = null): void
    {
```

Replace with:

```php
    public function updateApprovalStatus(int $leave_id, ?string $role = null, ?string $remarks = null, ?string $status = null, array $disapprovedDates = []): void
    {
```

Find (line 314):

```php
        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus) {
```

Replace with:

```php
        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus, $disapprovedDates) {
```

Find (lines 356-369):

```php
            if ($normalizedStatus === 'rejected') {
                $this->restoreCredits($leave_id);
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Rejected', 'updated_at' => Carbon::now()]);
                return;
            }

            if ($effectiveRole === 'HR') {
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Approved', 'updated_at' => Carbon::now()]);
            }
        });
```

Replace with:

```php
            if ($normalizedStatus === 'rejected') {
                $this->restoreCredits($leave_id);
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Rejected', 'updated_at' => Carbon::now()]);
                return;
            }

            if (in_array($effectiveRole, ['SH', 'DH'], true)) {
                $this->applyDateDisapprovals($leave_id, $effectiveRole, $disapprovedDates);
            }

            if ($effectiveRole === 'HR') {
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Approved', 'updated_at' => Carbon::now()]);
            }
        });
```

- [ ] **Step 4: Add `applyDateDisapprovals()`**

In `app/Services/LeaveApprovalService.php`, add this method immediately after `restoreCredits()` (currently ending at line 391, right before the class's closing `}`):

```php
    /**
     * Applies per-date disapproval flags for the SH or DH stage of a leave
     * application, on top of that stage's own overall approve/reject
     * decision - see
     * docs/superpowers/specs/2026-07-15-per-date-leave-disapproval-design.md.
     *
     * SH's action only ever records a suggestion (sh_flagged_disapprove) -
     * it never touches final_disapproved or credits. DH's action is the
     * final, operative verdict: $disapprovedDates is DH's complete desired
     * state for the application, and any date whose final_disapproved value
     * changes as a result has its credits restored (newly disapproved) or
     * re-deducted (overridden back to approved).
     *
     * @param array<int, array{date_applied_id: mixed, remark?: ?string}> $disapprovedDates
     */
    private function applyDateDisapprovals(int $leaveId, string $role, array $disapprovedDates): void
    {
        $remarkByDateId = collect($disapprovedDates)->mapWithKeys(
            fn($d) => [(int) $d['date_applied_id'] => $d['remark'] ?? null]
        );

        $dates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $leaveId)
            ->get(['id', 'leave_type_id', 'credits_deducted', 'final_disapproved']);

        if ($role === 'SH') {
            foreach ($dates as $date) {
                $flagged = $remarkByDateId->has($date->id);
                DB::table('hris.leave_date_applied')
                    ->where('id', $date->id)
                    ->update([
                        'sh_flagged_disapprove' => $flagged,
                        'sh_disapproval_remark' => $flagged ? $remarkByDateId->get($date->id) : null,
                    ]);
            }
            return;
        }

        // DH: $disapprovedDates is the complete final state for every date.
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['user_id']);

        foreach ($dates as $date) {
            $willBeDisapproved = $remarkByDateId->has($date->id);
            $wasDisapproved    = (bool) $date->final_disapproved;

            if ($wasDisapproved === $willBeDisapproved) {
                continue;
            }

            DB::table('hris.leave_date_applied')
                ->where('id', $date->id)
                ->update([
                    'final_disapproved'     => $willBeDisapproved,
                    'dh_disapproval_remark' => $willBeDisapproved ? $remarkByDateId->get($date->id) : null,
                ]);

            if (!$date->leave_type_id) {
                continue;
            }

            $creditQuery = DB::table('hris.leave_credits')
                ->where('user_id', $app->user_id)
                ->where('leave_id', $date->leave_type_id);

            if ($willBeDisapproved) {
                $creditQuery->increment('based_credit', $date->credits_deducted);
            } else {
                $creditQuery->decrement('based_credit', $date->credits_deducted);
            }
        }
    }
```

- [ ] **Step 5: Wire the request field into the controller**

In `app/Http/Controllers/LeaveApplicationController.php`, find (lines 176-184):

```php
    public function updateApprovalStatus(Request $request)
    {
        try {
            $this->leaveApprovalService->updateApprovalStatus($request->id, null, $request->remarks, $request->status);
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

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php`
Expected: `Tests: 5 passed`.

- [ ] **Step 7: Commit**

```bash
git add app/Services/LeaveApprovalService.php app/Http/Controllers/LeaveApplicationController.php tests/Feature/LeaveDateDisapprovalTest.php
git commit -m "feat: let SH suggest and DH finalize per-date leave disapprovals"
```

---

### Task 3: Undo reversal for DH, and remove the dead phantom-column block

**Files:**
- Modify: `app/Services/LeaveApprovalService.php:258-293` (`undoApproval`), add new private method after `applyDateDisapprovals`
- Modify: `tests/Feature/LeaveDateDisapprovalTest.php`

The existing `undoApproval()` deletes rows from `hris.leave_application_logs` filtered by a `type` column that does not exist on that table (confirmed against the live schema earlier — it only has `id, leave_application_id, origin, destination, remarks, date_action_taken, created_at, updated_at`). That block runs inside the same `DB::transaction()` we're about to extend with the new per-date credit reversal — left in place, it throws `Unknown column 'type'` and rolls back the *entire* undo, including the new reversal logic this task adds. It has to come out for DH-undo to work at all, not just as unrelated cleanup.

- [ ] **Step 1: Write the failing test**

Add this method to `tests/Feature/LeaveDateDisapprovalTest.php`, just before the class's closing `}`:

```php
    /** @test */
    public function dh_undoing_their_approval_reverses_disapproved_dates_and_credits()
    {
        $seed = $this->seedApplication();

        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);
        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Agreed',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Confirmed'],
            ],
        ])->assertOk();

        $this->assertEqualsWithDelta(6.000, $this->creditBalance(), 0.001);

        $response = $this->withoutMiddleware()->postJson('/undo_approval', ['id' => $seed['app_id']]);
        $response->assertOk();

        $reverted = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(0, (int) $reverted->final_disapproved);
        $this->assertNull($reverted->dh_disapproval_remark);
        // SH's original suggestion survives the undo.
        $this->assertEquals(1, (int) $reverted->sh_flagged_disapprove);
        $this->assertEquals('No coverage', $reverted->sh_disapproval_remark);

        // Credit re-deducted back to the pre-DH-approval balance.
        $this->assertEqualsWithDelta(5.000, $this->creditBalance(), 0.001);

        $dhRow = DB::table('hris.leave_application_approvals')->where('leave_application_id', $seed['app_id'])->where('role', 'DH')->first();
        $this->assertEquals('pending', $dhRow->status);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php --filter dh_undoing_their_approval_reverses_disapproved_dates_and_credits`
Expected: FAIL — the request throws (the phantom `type`-column delete errors and the whole transaction rolls back), or at minimum `final_disapproved`/credits are left unchanged since no reversal logic exists yet.

- [ ] **Step 3: Replace `undoApproval()`**

In `app/Services/LeaveApprovalService.php`, find (lines 258-293):

```php
    public function undoApproval(int $leaveId): void
    {
        if (!$this->canUndoApproval($leaveId)) {
            throw new \RuntimeException('You are not allowed to undo this approval, or a subsequent stage has already approved.');
        }

        $myApproval = $this->findUserApprovedStep($leaveId);

        $roleToLogType = ['SH' => 1, 'DH' => 2, 'HR' => 3];
        $logTypeFrom   = $roleToLogType[$myApproval->role] ?? null;

        DB::transaction(function () use ($leaveId, $myApproval, $logTypeFrom) {
            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leaveId)
                ->where('role', $myApproval->role)
                ->update([
                    'status'      => 'pending',
                    'approved_at' => null,
                    'updated_at'  => Carbon::now(),
                ]);

            // Revert application status if it was fully approved (HR undid their step)
            DB::table('hris.leave_applications')
                ->where('id', $leaveId)
                ->where('status', 'Approved')
                ->update(['status' => 'Pending', 'updated_at' => Carbon::now()]);

            // Remove this stage's log and any downstream forwarding logs
            if ($logTypeFrom !== null) {
                DB::table('hris.leave_application_logs')
                    ->where('leave_application_id', $leaveId)
                    ->where('type', '>=', $logTypeFrom)
                    ->delete();
            }
        });
    }
```

Replace with:

```php
    public function undoApproval(int $leaveId): void
    {
        if (!$this->canUndoApproval($leaveId)) {
            throw new \RuntimeException('You are not allowed to undo this approval, or a subsequent stage has already approved.');
        }

        $myApproval = $this->findUserApprovedStep($leaveId);

        DB::transaction(function () use ($leaveId, $myApproval) {
            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leaveId)
                ->where('role', $myApproval->role)
                ->update([
                    'status'      => 'pending',
                    'approved_at' => null,
                    'updated_at'  => Carbon::now(),
                ]);

            // Revert application status if it was fully approved (HR undid their step)
            DB::table('hris.leave_applications')
                ->where('id', $leaveId)
                ->where('status', 'Approved')
                ->update(['status' => 'Pending', 'updated_at' => Carbon::now()]);

            if ($myApproval->role === 'DH') {
                $this->reverseDhDateDisapprovals($leaveId);
            }
        });
    }
```

- [ ] **Step 4: Add `reverseDhDateDisapprovals()`**

In `app/Services/LeaveApprovalService.php`, add this method immediately after `applyDateDisapprovals()` (added in Task 2, Step 4), right before the class's closing `}`:

```php
    /**
     * Reverses every date the Division Head disapproved on this
     * application, restoring credits that were re-deducted and clearing
     * final_disapproved - called when a DH undoes their own approval.
     * sh_flagged_disapprove/sh_disapproval_remark are left untouched, so
     * when DH redoes their review, the Supervisor's original suggestion is
     * still there to work from.
     */
    private function reverseDhDateDisapprovals(int $leaveId): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['user_id']);

        $disapprovedDates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $leaveId)
            ->where('final_disapproved', 1)
            ->get(['id', 'leave_type_id', 'credits_deducted']);

        foreach ($disapprovedDates as $date) {
            if ($date->leave_type_id) {
                DB::table('hris.leave_credits')
                    ->where('user_id', $app->user_id)
                    ->where('leave_id', $date->leave_type_id)
                    ->decrement('based_credit', $date->credits_deducted);
            }

            DB::table('hris.leave_date_applied')
                ->where('id', $date->id)
                ->update([
                    'final_disapproved'     => false,
                    'dh_disapproval_remark' => null,
                ]);
        }
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php`
Expected: `Tests: 6 passed`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/LeaveApprovalService.php tests/Feature/LeaveDateDisapprovalTest.php
git commit -m "fix: reverse per-date disapprovals on DH undo, remove dead leave_application_logs.type reference"
```

---

### Task 4: Expose per-date flags and the viewer's current approval role via the API

**Files:**
- Modify: `app/Services/LeaveApplicationService.php:293-298` (`findById`'s dates select)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:354-362` (`getLeaveApplication`)
- Modify: `tests/Feature/LeaveDateDisapprovalTest.php`

- [ ] **Step 1: Write the failing test**

Add this method to `tests/Feature/LeaveDateDisapprovalTest.php`, just before the class's closing `}`:

```php
    /** @test */
    public function get_leave_application_exposes_per_date_flags_and_current_approval_role()
    {
        $seed = $this->seedApplication();
        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);

        $this->actAsSh();
        $response = $this->withoutMiddleware()->getJson('/leave-applications/' . $seed['app_id']);
        $response->assertOk();
        $response->assertJson(['current_approval_role' => 'SH']);

        $dates = collect($response->json('dates'));
        $flagged = $dates->firstWhere('id', $seed['date_ids'][0]);
        $this->assertTrue($flagged['sh_flagged_disapprove']);
        $this->assertEquals('No coverage', $flagged['sh_disapproval_remark']);
        $this->assertFalse($flagged['final_disapproved']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php --filter get_leave_application_exposes_per_date_flags_and_current_approval_role`
Expected: FAIL — the response has no `current_approval_role` key and each `dates[]` entry has no `id`, `sh_flagged_disapprove`, `sh_disapproval_remark`, or `final_disapproved` keys yet.

- [ ] **Step 3: Add the new columns to `findById()`'s dates select**

In `app/Services/LeaveApplicationService.php`, find (lines 292-298):

```php
        // Dates with leave type info
        $dateRows = DB::table('hris.leave_date_applied as lda')
            ->leftJoin('hris.leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
            ->where('lda.leave_application_id', $id)
            ->select('lda.id', 'lda.date_applied', 'lda.hours', 'lda.leave_type_id', 'lda.status', 'lt.description as leave_type_name')
            ->orderBy('lda.date_applied', 'asc')
            ->get();
```

Replace with:

```php
        // Dates with leave type info
        $dateRows = DB::table('hris.leave_date_applied as lda')
            ->leftJoin('hris.leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
            ->where('lda.leave_application_id', $id)
            ->select(
                'lda.id',
                'lda.date_applied',
                'lda.hours',
                'lda.leave_type_id',
                'lda.status',
                'lt.description as leave_type_name',
                'lda.sh_flagged_disapprove',
                'lda.sh_disapproval_remark',
                'lda.final_disapproved',
                'lda.dh_disapproval_remark'
            )
            ->orderBy('lda.date_applied', 'asc')
            ->get();
```

- [ ] **Step 4: Expose the new fields in `getLeaveApplication()`**

In `app/Http/Controllers/LeaveApplicationController.php`, find (lines 354-362):

```php
            'can_undo'        => $this->leaveApprovalService->canUndoApproval($id),
            'dates'           => collect($leave->dates ?? [])->map(fn($d) => [
                'date'          => $d->date_applied,
                'duration'      => $d->hours,
                'leave_type_id' => $d->leave_type_id ?? null,
                'leave_type'    => $d->leave_type_name ?? '',
                'is_cancelled'  => ($d->status ?? '') === 'cancelled',
            ])->values(),
        ]);
```

Replace with:

```php
            'can_undo'              => $this->leaveApprovalService->canUndoApproval($id),
            'current_approval_role' => $this->leaveApprovalService->resolveCurrentApprovalRoleForLeave($id),
            'dates'                 => collect($leave->dates ?? [])->map(fn($d) => [
                'id'                    => $d->id,
                'date'                  => $d->date_applied,
                'duration'              => $d->hours,
                'leave_type_id'         => $d->leave_type_id ?? null,
                'leave_type'            => $d->leave_type_name ?? '',
                'is_cancelled'          => ($d->status ?? '') === 'cancelled',
                'sh_flagged_disapprove' => (bool) ($d->sh_flagged_disapprove ?? false),
                'sh_disapproval_remark' => $d->sh_disapproval_remark ?? null,
                'final_disapproved'     => (bool) ($d->final_disapproved ?? false),
                'dh_disapproval_remark' => $d->dh_disapproval_remark ?? null,
            ])->values(),
        ]);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php`
Expected: `Tests: 7 passed`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/LeaveApplicationService.php app/Http/Controllers/LeaveApplicationController.php tests/Feature/LeaveDateDisapprovalTest.php
git commit -m "feat: expose per-date disapproval flags and current approval role via the leave application API"
```

---

### Task 5: UI — interactive disapprove column in the approval modal

**Files:**
- Modify: `resources/views/modals/leave.blade.php:171-185` (Dates Applied table header)
- Modify: `resources/views/js/leave_approval.blade.php` (`renderLeaveModal`, new helpers, `handleApproval`)

- [ ] **Step 1: Add the "Disapprove" column to the Dates Applied table header**

In `resources/views/modals/leave.blade.php`, find (lines 171-185):

```html
                <!-- Dates Applied -->
                <div class="mt-3">
                    <p><strong>Dates Applied:</strong></p>
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Leave Type</th>
                            <th style="width: 30%;">Date</th>
                            <th style="width: 25%;">Duration</th>
                        </tr>
                        </thead>
                        <tbody id="dates_applied_body">
                        </tbody>
                    </table>
                </div>
```

Replace with:

```html
                <!-- Dates Applied -->
                <div class="mt-3">
                    <p><strong>Dates Applied:</strong></p>
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 26%;">Leave Type</th>
                            <th style="width: 18%;">Date</th>
                            <th style="width: 14%;">Duration</th>
                            <th style="width: 42%;">Disapprove</th>
                        </tr>
                        </thead>
                        <tbody id="dates_applied_body">
                        </tbody>
                    </table>
                </div>
```

- [ ] **Step 2: Add render helpers and wire them into `renderLeaveModal()`**

In `resources/views/js/leave_approval.blade.php`, find (lines 128-171):

```javascript
    function renderLeaveModal(data) {
        $('#leave_name').text(data.name);
        $('#leave_type').text(data.type);
        $('#leave_commutation').text(data.commutation);
        $('#leave_details_container').html(renderLeaveDetailsHtml(data));

        const allAttachments = (data.attachments && data.attachments.length > 0)
            ? data.attachments
            : (data.attachment_url ? [{ id: null, url: data.attachment_url, original_name: 'View Attachment' }] : []);
        if (allAttachments.length > 0) {
            const links = allAttachments.map(a =>
                `<a href="${a.url}" target="_blank" class="me-2"><i class="bi bi-paperclip"></i> ${a.original_name}</a>`
            ).join('');
            $('#view_attachment_row').html(`<p><strong>Attachments:</strong> ${links}</p>`).show();
        } else {
            $('#view_attachment_row').hide();
        }

        $('#dates_applied_body').html(data.dates.map(d =>
            `<tr><td>${d.leave_type || ''}</td><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td></tr>`
        ).join(""));

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

        $('#remarks_history').html(remarks);
        const canAct = activeFilter === 'pending';
        $('#approveLeaveBtn, #rejectLeaveBtn').toggle(canAct);
        $('#undoApprovalBtn').toggleClass('d-none', !data.can_undo);

        $('#viewLeaveModal').modal('show');
    }
```

Replace with:

```javascript
    function renderDateDisapproveCell(d, role) {
        if (role === 'SH' || role === 'DH') {
            const checked = role === 'SH'
                ? !!d.sh_flagged_disapprove
                : (!!d.sh_flagged_disapprove || !!d.final_disapproved);
            const remark = role === 'SH'
                ? (d.sh_disapproval_remark || '')
                : (d.dh_disapproval_remark || d.sh_disapproval_remark || '');
            const label = role === 'SH' ? 'Flag for disapproval' : 'Disapprove this date';

            return `
                <div class="form-check">
                    <input type="checkbox" class="form-check-input date-disapprove-cb" data-date-id="${d.id}" id="dd_${d.id}" ${checked ? 'checked' : ''}>
                    <label class="form-check-label small" for="dd_${d.id}">${label}</label>
                </div>
                <input type="text" class="form-control form-control-sm mt-1 date-disapprove-remark" data-date-id="${d.id}" placeholder="Reason (optional)" value="${remark}" style="${checked ? '' : 'display:none;'}">
            `;
        }

        return d.final_disapproved ? `<span class="badge bg-danger">Disapproved</span>` : '';
    }

    $(document).on('change', '.date-disapprove-cb', function () {
        $(this).closest('td').find('.date-disapprove-remark').toggle(this.checked);
    });

    function collectDisapprovedDates() {
        const result = [];
        $('#dates_applied_body .date-disapprove-cb:checked').each(function () {
            const remark = $(this).closest('td').find('.date-disapprove-remark').val().trim();
            result.push({ date_applied_id: $(this).data('date-id'), remark: remark || null });
        });
        return result;
    }

    function renderLeaveModal(data) {
        $('#leave_name').text(data.name);
        $('#leave_type').text(data.type);
        $('#leave_commutation').text(data.commutation);
        $('#leave_details_container').html(renderLeaveDetailsHtml(data));

        const allAttachments = (data.attachments && data.attachments.length > 0)
            ? data.attachments
            : (data.attachment_url ? [{ id: null, url: data.attachment_url, original_name: 'View Attachment' }] : []);
        if (allAttachments.length > 0) {
            const links = allAttachments.map(a =>
                `<a href="${a.url}" target="_blank" class="me-2"><i class="bi bi-paperclip"></i> ${a.original_name}</a>`
            ).join('');
            $('#view_attachment_row').html(`<p><strong>Attachments:</strong> ${links}</p>`).show();
        } else {
            $('#view_attachment_row').hide();
        }

        $('#dates_applied_body').html(data.dates.map(d =>
            `<tr><td>${d.leave_type || ''}</td><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td><td>${renderDateDisapproveCell(d, data.current_approval_role)}</td></tr>`
        ).join(""));

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

        $('#remarks_history').html(remarks);
        const canAct = activeFilter === 'pending';
        $('#approveLeaveBtn, #rejectLeaveBtn').toggle(canAct);
        $('#undoApprovalBtn').toggleClass('d-none', !data.can_undo);

        $('#viewLeaveModal').modal('show');
    }
```

- [ ] **Step 3: Send the collected disapproved dates when approving/rejecting**

In `resources/views/js/leave_approval.blade.php`, find (lines 190-207):

```javascript
    function handleApproval() {
        const button = this;
        const originalHtml = button.innerHTML;
        $(button).prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        fetch('{{ route('updateApprovalStatus') }}', {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")},
            body: JSON.stringify({id: leave_id, remarks: $("#leave_remarks").val().trim(), status: this.value})
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

- [ ] **Step 4: Verify by rendering the page**

Add a temporary test, run it, then delete it (this feature has no dedicated permanent view-render test elsewhere in this codebase — the JSON shape and backend logic are already covered by Tasks 2-4):

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TempLeaveApprovalDisapproveUiTest extends TestCase
{
    /** @test */
    public function the_leave_approval_page_renders_the_disapprove_column()
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId(1);

        $response = $this->get('/leave_approval');
        $response->assertOk();
        $response->assertSee('Disapprove', false);
        $response->assertSee('date-disapprove-cb', false);
        $response->assertSee('collectDisapprovedDates', false);
    }
}
```

Save it to `tests/Feature/TempLeaveApprovalDisapproveUiTest.php`, run:

Run: `php artisan view:clear && php artisan test tests/Feature/TempLeaveApprovalDisapproveUiTest.php`
Expected: `Tests: 1 passed`.

Then delete the file: `rm tests/Feature/TempLeaveApprovalDisapproveUiTest.php`

- [ ] **Step 5: Commit**

```bash
git add resources/views/modals/leave.blade.php resources/views/js/leave_approval.blade.php
git commit -m "feat: add interactive per-date disapprove checkboxes to the leave approval modal"
```

---

### Task 6: UI — read-only "Disapproved" badge in the employee's own view

**Files:**
- Modify: `resources/views/js/leave_application.blade.php:840-847`

- [ ] **Step 1: Add the badge and the matching 4th cell**

`modals/leave.blade.php` is shared between the approver's view and the employee's own "My Leave Applications" view, so after Task 5 the Dates Applied table always has 4 columns. This view must render a matching 4th `<td>` — read-only, since an employee is never the SH/DH viewer of their own application.

In `resources/views/js/leave_application.blade.php`, find (lines 840-847):

```javascript
            $('#dates_applied_body').html(
                data.dates.map(d => {
                    const cancelledBadge = d.is_cancelled
                        ? ` <span class="badge bg-danger" style="font-size:.7rem">Cancelled</span>`
                        : '';
                    return `<tr><td>${d.leave_type || ''}${cancelledBadge}</td><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td></tr>`;
                }).join("")
            );
```

Replace with:

```javascript
            $('#dates_applied_body').html(
                data.dates.map(d => {
                    const cancelledBadge = d.is_cancelled
                        ? ` <span class="badge bg-danger" style="font-size:.7rem">Cancelled</span>`
                        : '';
                    const disapprovedBadge = d.final_disapproved
                        ? `<span class="badge bg-danger">Disapproved</span>`
                        : '';
                    return `<tr><td>${d.leave_type || ''}${cancelledBadge}</td><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td><td>${disapprovedBadge}</td></tr>`;
                }).join("")
            );
```

- [ ] **Step 2: Verify by rendering the page**

Add a temporary test, run it, then delete it:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TempLeaveApplicationDisapproveBadgeUiTest extends TestCase
{
    /** @test */
    public function the_leave_application_page_renders_the_disapproved_badge_logic()
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId(1);

        $response = $this->get('/leave_application');
        $response->assertOk();
        $response->assertSee('disapprovedBadge', false);
        $response->assertSee('Disapproved', false);
    }
}
```

Save it to `tests/Feature/TempLeaveApplicationDisapproveBadgeUiTest.php`, run:

Run: `php artisan view:clear && php artisan test tests/Feature/TempLeaveApplicationDisapproveBadgeUiTest.php`
Expected: `Tests: 1 passed`.

Then delete the file: `rm tests/Feature/TempLeaveApplicationDisapproveBadgeUiTest.php`

- [ ] **Step 3: Commit**

```bash
git add resources/views/js/leave_application.blade.php
git commit -m "feat: show a read-only Disapproved badge on the employee's own leave application view"
```

---

### Task 7: Final regression pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full new test file plus adjacent leave/credit suites**

Run each separately:

```bash
php artisan test tests/Feature/LeaveDateDisapprovalTest.php
php artisan test tests/Unit/LeaveCreditServiceTest.php
```

Expected: both suites pass with no failures — `LeaveDateDisapprovalTest` at `Tests: 7 passed`.

- [ ] **Step 2: Confirm no stray temp test files remain**

Run: `git status --short`
Expected: no untracked `Temp*Test.php` files under `tests/Feature/`, and no other unexpected modified files.

- [ ] **Step 3: Manual smoke test**

Since this touches an interactive UI flow across two pages, do a manual pass in the browser once the branch is deployed locally: file a multi-date leave application, flag a date as SH, uphold it as DH (confirm credits restore in `leave_credits`), then undo the DH approval (confirm credits re-deduct and the SH flag is still shown pre-checked).
