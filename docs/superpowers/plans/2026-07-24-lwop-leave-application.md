# LWOP Leave Application Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a third "LWOP" tab to the leave application page that reuses the entire Leave Application filing flow but skips the credit-balance check and never deducts credits.

**Architecture:** A new `leave_applications.is_lwop` boolean column threads through the existing insert/update service methods (`LeaveApplicationService`), controller (`LeaveApplicationController`), and approval service (`LeaveApprovalService`) as an optional flag. No new tables, no new controllers, no new named routes — the flag rides inside the existing request payload (insert) / existing validated fields (update) and as an existing query string param (list). The frontend reuses the existing filing modal (`#file_leave_modal`) via a hidden `is_lwop` input that toggles the modal title and disables the client-side balance guard, and reuses `editApplication()`/`viewApplication()`/`printApplication()`/`cancelApplication()` unchanged since those operate on an application `id` regardless of LWOP status.

**Tech Stack:** Laravel 8 (PHP), Blade, jQuery, Bootstrap 5 tabs/modals. No automated test suite exists on this project — `RefreshDatabase` is unsafe here because there is no separate test database (it runs `migrate:fresh` against the shared live `hris` database). Every task below is verified manually via `php artisan tinker` and/or the actual browser UI instead of PHPUnit.

**Reference spec:** `docs/superpowers/specs/2026-07-24-lwop-leave-application-design.md`

**Note on deviation from spec:** The spec called for two new routes (`POST /insert_lwop_applications`, `GET /get_lwop_applications`). While reading the controller it became clear the existing `insertLeaveApplication` route already forwards the raw decoded JSON body straight to the service unfiltered, and the existing `getLeaveApplications` route can simply accept an added `is_lwop` query string parameter — so no new route entries are needed at all, only an added parameter carried through the existing ones. This is a pure implementation simplification (fewer routes to maintain); it does not change any behavior described in the spec.

---

### Task 1: Add `is_lwop` column to `leave_applications`

**Files:**
- Create: `database/migrations/2026_07_24_150000_add_is_lwop_to_leave_applications_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->boolean('is_lwop')->default(false)->after('status');
        });
    }

    public function down()
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('is_lwop');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_24_150000_add_is_lwop_to_leave_applications_table ... DONE` (or equivalent success line) in the output.

- [ ] **Step 3: Verify the column exists**

Run: `php artisan tinker --execute="echo Schema::hasColumn('leave_applications', 'is_lwop') ? 'yes' : 'no';"`
Expected: `yes`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_24_150000_add_is_lwop_to_leave_applications_table.php
git commit -m "feat: add is_lwop column to leave_applications"
```

---

### Task 2: Skip credit check/deduction in `LeaveApplicationService` when `is_lwop`

**Files:**
- Modify: `app/Services/LeaveApplicationService.php:71-188` (`insertLeaveApplication`)
- Modify: `app/Services/LeaveApplicationService.php:190-327` (`updateLeaveApplication`)

- [ ] **Step 1: Update `insertLeaveApplication`**

Replace the method body (lines 71-188) with:

```php
    public function insertLeaveApplication(array $data)
    {
        return DB::transaction(function () use ($data) {
            $isLwop   = (bool) ($data['is_lwop'] ?? false);
            $dates    = $data['dates']; // each: { date_applied, hours, leave_type_id }
            $leaveIds = collect($dates)->pluck('leave_type_id')->unique()->filter()->values()->toArray();
            $n        = count($leaveIds);

            if ($n === 0) {
                throw new \Exception('No leave type selected for any date.');
            }

            // Group dates by leave_type for per-type credit calculation
            $datesByType = collect($dates)->groupBy('leave_type_id');

            $leaveTypes = DB::table('leave_types')
                ->whereIn('id', $leaveIds)
                ->get(['id', 'description', 'days_prior'])
                ->keyBy('id');

            // Validate days_prior per type
            $today = Carbon::today();
            foreach ($leaveIds as $leaveId) {
                $daysPrior = (int) ($leaveTypes[$leaveId]->days_prior ?? 0);
                if ($daysPrior <= 0) continue;

                $earliest = $today->copy()->addDays($daysPrior)->format('Y-m-d');
                $desc     = $leaveTypes[$leaveId]->description;

                foreach (($datesByType[$leaveId] ?? collect()) as $date) {
                    if ($date['date_applied'] < $earliest) {
                        throw new \Exception(
                            "{$desc} requires at least {$daysPrior} day(s) advance notice. Earliest allowed date is {$earliest}."
                        );
                    }
                }
            }

            // Validate credit balance per type (credits proportional to actual days per type)
            // Skipped entirely for LWOP — an LWOP filing never checks or deducts credits.
            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = round(($typeHours / 8) * self::CREDIT_RATE_PER_DAY, 3);

                    $credit    = DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->first(['based_credit', 'used_credit']);
                    $available = $credit ? ($credit->based_credit - $credit->used_credit) : null;

                    $desc = $leaveTypes[$leaveId]->description;

                    if ($available === null) {
                        throw new \Exception("No leave credits found for {$desc}.");
                    }

                    if ($available < $typeCredits) {
                        throw new \Exception("Insufficient {$desc} credits. Need {$typeCredits}, you have {$available}.");
                    }
                }
            }

            // ONE leave_application record
            $appId = DB::table('leave_applications')->insertGetId([
                'user_id'             => Auth::id(),
                'commutation'         => $data['commutation'] ?? null,
                'attachment'          => $data['attachment'] ?? null,
                'applicant_signature' => $data['applicant_signature'],
                'status'              => 'pending',
                'is_lwop'             => $isLwop,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // Leave details (without leave_id and credits_deducted — now per date)
            $detailsMap = $data['details_map'] ?? [];
            foreach ($leaveIds as $leaveId) {
                $typeDetail = $detailsMap[(string) $leaveId] ?? [];
                if (empty($typeDetail['detail'])) continue;

                DB::table('leave_details')->insert([
                    'leave_application_id' => $appId,
                    'detail'               => $typeDetail['detail'],
                    'extra'                => $typeDetail['extra'] ?? null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            // Deduct credits per leave type — skipped entirely for LWOP.
            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = round(($typeHours / 8) * self::CREDIT_RATE_PER_DAY, 3);

                    DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->decrement('based_credit', $typeCredits);
                }
            }

            // Insert dates with leave_type_id, status, and per-date credits_deducted.
            // credits_deducted is always 0 for LWOP — this also makes the downstream
            // approval-rejection credit-restore logic a natural no-op for LWOP rows,
            // since it restores by summing credits_deducted.
            DB::table('leave_date_applied')->insert(
                collect($dates)->map(fn($d) => [
                    'leave_application_id' => $appId,
                    'leave_type_id'        => $d['leave_type_id'],
                    'date_applied'         => $d['date_applied'],
                    'hours'                => $d['hours'],
                    'status'               => 'active',
                    'credits_deducted'     => $isLwop ? 0 : round(($d['hours'] / 8) * self::CREDIT_RATE_PER_DAY, 3),
                ])->toArray()
            );

            // Approval chain
            DB::table('leave_application_approvals')->insert([
                ['leave_application_id' => $appId, 'approver_id' => Auth::user()->section,  'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
                ['leave_application_id' => $appId, 'approver_id' => Auth::user()->division, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
                ['leave_application_id' => $appId, 'approver_id' => 15,                     'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ]);

            return ['app_id' => $appId];
        });
    }
```

- [ ] **Step 2: Update `updateLeaveApplication`**

Replace the method body (lines 190-327) with:

```php
    public function updateLeaveApplication(int $appId, array $data): void
    {
        DB::transaction(function () use ($appId, $data) {
            $isLwop = (bool) ($data['is_lwop'] ?? false);

            $app = DB::table('leave_applications')
                ->where('id', $appId)
                ->where('user_id', Auth::id())
                ->first(['id', 'status']);

            if (!$app) {
                throw new \Exception('Leave application not found.');
            }

            $shApproved = DB::table('leave_application_approvals')
                ->where('leave_application_id', $appId)
                ->where('role', 'SH')
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->exists();

            if ($shApproved) {
                throw new \Exception('Cannot edit: this application has already been forwarded for approval.');
            }

            // Restore credits from existing dates (grouped by leave_type_id).
            // For an LWOP application every existing credits_deducted is already 0,
            // so this naturally restores nothing.
            $oldDates = DB::table('hris.leave_date_applied')
                ->where('leave_application_id', $appId)
                ->get(['leave_type_id', 'credits_deducted']);

            $oldCreditsByType = $oldDates->groupBy('leave_type_id');
            foreach ($oldCreditsByType as $typeId => $rows) {
                if (!$typeId) continue;
                DB::table('leave_credits')
                    ->where('user_id', Auth::id())
                    ->where('leave_id', $typeId)
                    ->increment('based_credit', $rows->sum('credits_deducted'));
            }

            DB::table('leave_details')->where('leave_application_id', $appId)->delete();
            DB::table('leave_date_applied')->where('leave_application_id', $appId)->delete();

            // Recalculate with new data
            $dates    = $data['dates'];
            $leaveIds = collect($dates)->pluck('leave_type_id')->unique()->filter()->values()->toArray();
            $n        = count($leaveIds);

            if ($n === 0) {
                throw new \Exception('No leave type selected for any date.');
            }

            $datesByType = collect($dates)->groupBy('leave_type_id');

            $leaveTypes = DB::table('leave_types')
                ->whereIn('id', $leaveIds)
                ->get(['id', 'description', 'days_prior'])
                ->keyBy('id');

            $today = Carbon::today();
            foreach ($leaveIds as $leaveId) {
                $daysPrior = (int) ($leaveTypes[$leaveId]->days_prior ?? 0);
                if ($daysPrior <= 0) continue;

                $earliest = $today->copy()->addDays($daysPrior)->format('Y-m-d');
                $desc     = $leaveTypes[$leaveId]->description;

                foreach (($datesByType[$leaveId] ?? collect()) as $date) {
                    if ($date['date_applied'] < $earliest) {
                        throw new \Exception(
                            "{$desc} requires at least {$daysPrior} day(s) advance notice. Earliest allowed date is {$earliest}."
                        );
                    }
                }
            }

            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = round(($typeHours / 8) * self::CREDIT_RATE_PER_DAY, 3);

                    $credit    = DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->first(['based_credit', 'used_credit']);
                    $available = $credit ? ($credit->based_credit - $credit->used_credit) : null;

                    $desc = $leaveTypes[$leaveId]->description;

                    if ($available === null) {
                        throw new \Exception("No leave credits found for {$desc}.");
                    }

                    if ($available < $typeCredits) {
                        throw new \Exception("Insufficient {$desc} credits. Need {$typeCredits}, you have {$available}.");
                    }
                }
            }

            // Insert new leave_details (without leave_id)
            $detailsMap = $data['details_map'] ?? [];
            foreach ($leaveIds as $leaveId) {
                $typeDetail = $detailsMap[(string) $leaveId] ?? [];
                if (empty($typeDetail['detail'])) continue;

                DB::table('leave_details')->insert([
                    'leave_application_id' => $appId,
                    'detail'               => $typeDetail['detail'],
                    'extra'                => $typeDetail['extra'] ?? null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            // Deduct credits per type — skipped entirely for LWOP.
            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = round(($typeHours / 8) * self::CREDIT_RATE_PER_DAY, 3);

                    DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->decrement('based_credit', $typeCredits);
                }
            }

            // Insert new dates
            DB::table('leave_date_applied')->insert(
                collect($dates)->map(fn($d) => [
                    'leave_application_id' => $appId,
                    'leave_type_id'        => $d['leave_type_id'],
                    'date_applied'         => $d['date_applied'],
                    'hours'                => $d['hours'],
                    'status'               => 'active',
                    'credits_deducted'     => $isLwop ? 0 : round(($d['hours'] / 8) * self::CREDIT_RATE_PER_DAY, 3),
                ])->toArray()
            );

            DB::table('leave_applications')->where('id', $appId)->update([
                'commutation'         => $data['commutation'] ?? null,
                'applicant_signature' => $data['applicant_signature'],
                'is_lwop'             => $isLwop,
                'updated_at'          => now(),
            ]);
        });
    }
```

- [ ] **Step 3: Verify with tinker — LWOP insert skips the credit check**

Run (adjust `user_id`/`leave_type_id` to a real employee + leave type with **zero** available balance in your dev data):

```
php artisan tinker --execute="
Auth::loginUsingId(<some_user_id>);
\$svc = app(\App\Services\LeaveApplicationService::class);
\$result = \$svc->insertLeaveApplication([
    'is_lwop' => true,
    'dates' => [['date_applied' => now()->addDays(3)->format('Y-m-d'), 'hours' => 8, 'leave_type_id' => <leave_type_id>]],
    'commutation' => 'Not Requested',
    'applicant_signature' => 'data:image/png;base64,iVBORw0KGgo=',
]);
echo json_encode(\$result);
echo DB::table('leave_applications')->find(\$result['app_id'])->is_lwop;
echo DB::table('leave_date_applied')->where('leave_application_id', \$result['app_id'])->value('credits_deducted');
"
```

Expected: no exception thrown, `is_lwop` prints `1`, `credits_deducted` prints `0`.

- [ ] **Step 4: Commit**

```bash
git add app/Services/LeaveApplicationService.php
git commit -m "feat: skip credit check/deduction in LeaveApplicationService when is_lwop"
```

---

### Task 3: Thread `is_lwop` through `LeaveApplicationController`

**Files:**
- Modify: `app/Http/Controllers/LeaveApplicationController.php:200-259` (`insertLeaveApplication`)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:414-441` (`updateLeaveApplication`)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:26-106` (`index`)
- Modify: `app/Http/Controllers/LeaveApplicationController.php:337-391` (`getLeaveApplication`)
- Modify: `app/Services/LeaveApplicationService.php:329-401` (`findById`) — needs `is_lwop` in its select

- [ ] **Step 1: Add `is_lwop` validation rule to `insertLeaveApplication`**

In `insertLeaveApplication` (around line 214-224), add the rule to the `Validator::make` array:

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
                'is_lwop'                   => 'nullable|boolean',
            ]);
```

(No other change is needed in this method — `$params` is passed to the service as the raw decoded array, so `is_lwop` already flows through once the frontend includes it in the JSON body.)

- [ ] **Step 2: Add `is_lwop` validation rule to `updateLeaveApplication`**

Replace the `$request->validate([...])` call (lines 417-427) with:

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
                'is_lwop'                    => 'sometimes|boolean',
            ]);
```

- [ ] **Step 3: Filter `index()` by `is_lwop`**

In `index()`, change the query build (lines 29-39) to:

```php
            $type    = $request->search_type;
            $isLwop  = $request->boolean('is_lwop');

            $query = DB::table('leave_applications as la')
                ->join('leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
                ->join('leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
                ->where('la.user_id', Auth::id())
                ->where('la.is_lwop', $isLwop)
                ->when($type, fn($q) => $q->where('lt.description', 'LIKE', "%{$type}%"))
                ->orderBy('la.id', 'DESC')
                ->select('la.id', 'la.status')
                ->distinct()
                ->get();
```

- [ ] **Step 4: Add `is_lwop` to `findById()`'s select and to the `getLeaveApplication()` response**

In `app/Services/LeaveApplicationService.php`, `findById()` (line 334), change:

```php
        $application = DB::table('hris.leave_applications as la')
            ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
            ->where('la.id', $id)
            ->select('la.id', 'u.fname', 'u.lname', 'u.mname', 'la.commutation', 'la.attachment', 'la.created_at', 'la.is_lwop')
            ->first();
```

In `LeaveApplicationController::getLeaveApplication()` (around line 348, right after `'commutation' => $leave->commutation,`), add:

```php
            'commutation'     => $leave->commutation,
            'is_lwop'         => (bool) $leave->is_lwop,
```

- [ ] **Step 5: Verify with tinker**

```
php artisan tinker --execute="
Auth::loginUsingId(<same_user_id_as_task_2>);
\$ctrl = app(\App\Http\Controllers\LeaveApplicationController::class);
\$response = \$ctrl->index(new \Illuminate\Http\Request(['is_lwop' => 1]));
echo \$response->getContent();
"
```

Expected: JSON `results` array containing the application inserted in Task 2's verification step (and none of the user's regular, non-LWOP applications).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LeaveApplicationController.php app/Services/LeaveApplicationService.php
git commit -m "feat: thread is_lwop through LeaveApplicationController insert/update/index/show"
```

---

### Task 4: Tag `is_lwop` in the approval queue

**Files:**
- Modify: `app/Services/LeaveApprovalService.php:82-95` (`$baseSelect` in `getLeavesForApproval`)

- [ ] **Step 1: Add `la.is_lwop` to the shared select**

Change:

```php
        $baseSelect = [
            'la.id',
            DB::raw("(SELECT GROUP_CONCAT(DISTINCT lt.description ORDER BY lt.id SEPARATOR ', ')
                      FROM hris.leave_date_applied lda
                      INNER JOIN hris.leave_types lt ON lda.leave_type_id = lt.id
                      WHERE lda.leave_application_id = la.id) as description"),
            DB::raw("(SELECT ld2.detail FROM hris.leave_details ld2
                      WHERE ld2.leave_application_id = la.id LIMIT 1) as detail"),
            'la.status',
            'la.created_at',
            'la.is_lwop',
            'u.fname',
            'u.mname',
            'u.lname',
        ];
```

(Only the `'la.is_lwop',` line is new — every branch of `getLeavesForApproval` reuses this same `$baseSelect` array, so this one change covers the `pending`, `approved_by_stage`, and `approved_all` filters.)

- [ ] **Step 2: Verify with tinker**

```
php artisan tinker --execute="
\$svc = app(\App\Services\LeaveApprovalService::class);
\$rows = \$svc->getLeavesForApproval(null, 'pending');
echo \$rows->first(fn(\$r) => \$r->id == <app_id_from_task_2>)->is_lwop ?? 'NOT FOUND';
"
```

Expected: `1`.

- [ ] **Step 3: Commit**

```bash
git add app/Services/LeaveApprovalService.php
git commit -m "feat: expose is_lwop in approval queue results"
```

---

### Task 5: Add the LWOP tab and pane

**Files:**
- Modify: `resources/views/leave_application.blade.php:34-122`

- [ ] **Step 1: Add the third tab button**

In the `<ul class="nav nav-tabs mb-3" id="leaveTab">` block (lines 34-47), add a third `<li>` after the Monetizations one:

```blade
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-lwop" data-bs-toggle="tab"
                    data-bs-target="#pane-lwop" type="button" role="tab">
                LWOP
            </button>
        </li>
```

- [ ] **Step 2: Add the third tab pane**

Inside `<div class="tab-content" id="leaveTabContent">`, after the Monetizations pane (`</div>` that closes `#pane-monetizations`, line 120) and before the closing `</div>` of `tab-content` (line 122), add:

```blade
        {{-- Tab 3: LWOP --}}
        <div class="tab-pane fade" id="pane-lwop" role="tabpanel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-0" style="color:#0f172a">LWOP Applications</h5>
                    <span style="font-size:.9rem;color:#64748b">Leave Without Pay — files even without available credits.</span>
                </div>
                <button class="btn btn-success btn-sm" id="btn_file_lwop">
                    <i class="bi bi-plus-lg me-1"></i> File LWOP
                </button>
            </div>
            <div class="table-responsive leave-app-table-shell">
                <table class="table text-center table-bordered align-middle">
                    <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Dates Applied</th>
                        <th>Status</th>
                        <th style="width:4%"></th>
                    </tr>
                    </thead>
                    <tbody id="lwop_history_list"></tbody>
                </table>
            </div>
            <div class="mt-3" id="lwop_pagination_container">
                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <div id="lwop_page_items_count" class="small text-muted"></div>
                    <nav aria-label="LWOP pagination">
                        <ul class="pagination pagination-sm mb-0" id="lwop_pagination_links"></ul>
                    </nav>
                    <div id="lwop_page_indicator" class="small text-muted"></div>
                </div>
            </div>
        </div>
```

- [ ] **Step 2: Verify in browser**

Run: start the app (`php artisan serve` or via WAMP), open `/leave_application`.
Expected: three tabs — "Leave Applications", "Monetizations", "LWOP" — the LWOP tab shows an empty "File LWOP" pane with no JS errors in the console (the button won't do anything yet — that's Task 7).

- [ ] **Step 3: Commit**

```bash
git add resources/views/leave_application.blade.php
git commit -m "feat: add LWOP tab and pane to leave application page"
```

---

### Task 6: Wire the filing modal for LWOP mode + view-modal badge

**Files:**
- Modify: `resources/views/modals/leave.blade.php:39-40` (`#file_leave_modal` title + hidden flag)
- Modify: `resources/views/modals/leave.blade.php:210-212` (`#viewLeaveModal` type row — LWOP badge)

- [ ] **Step 1: Add hidden `is_lwop` input and an id on the modal title**

Change (line 39-40):

```blade
            <div class="modal-body">
                <div class="modal-title-text" id="file_leave_modal_title">Leave Application</div>
                <input type="hidden" id="is_lwop_flag" value="0">
```

- [ ] **Step 2: Add an LWOP badge to the view modal's Type row**

Change (line 212):

```blade
                    <p><strong>Type:</strong> <span id="leave_type"></span> <span id="lwop_badge" class="badge bg-warning text-dark d-none">LWOP</span></p>
```

- [ ] **Step 3: Verify in browser**

Run: open `/leave_application`, click "File Leave" — modal title should read "Leave Application" and dev tools should show a hidden `#is_lwop_flag` input with value `0`.
Expected: no visual change to the existing "File Leave" flow (the badge/flag are invisible until Task 7 wires them up).

- [ ] **Step 4: Commit**

```bash
git add resources/views/modals/leave.blade.php
git commit -m "feat: add LWOP mode flag and badge to leave modals"
```

---

### Task 7: Frontend JS — LWOP mode, unfiltered leave-type dropdown, LWOP history

**Files:**
- Modify: `resources/views/js/leave_application.blade.php`

- [ ] **Step 1: Make `buildLeaveTypeOptions` skip the balance filter in LWOP mode**

Replace (around line 591-597):

```javascript
    function buildLeaveTypeOptions(selectedId) {
        const isLwop = $('#is_lwop_flag').val() === '1';
        return LEAVE_TYPES
            .filter(lt => isLwop || parseFloat(lt.balance) > 0)
            .map(lt =>
                `<option value="${lt.id}" ${lt.id === selectedId ? 'selected' : ''}>${lt.description}</option>`
            ).join('');
    }
```

- [ ] **Step 2: Add a `setLwopMode()` helper and call it from `clearForm()`**

Add this new function right before `function clearForm() {` (around line 1118):

```javascript
    function setLwopMode(isLwop) {
        $('#is_lwop_flag').val(isLwop ? '1' : '0');
        $('#file_leave_modal_title').text(isLwop ? 'LWOP Application' : 'Leave Application');
    }
```

Then update `clearForm()` (lines 1118-1133) to reset the flag, adding one line right after `editing_leave_id = null;`:

```javascript
    function clearForm() {
        editing_leave_id = null;
        setLwopMode(false);
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

- [ ] **Step 3: Add the "File LWOP" button handler**

In the `$(document).ready(function () { ... })` block, right after the existing `$("#btn_file_leave").click(...)` handler (lines 227-234), add:

```javascript
        $("#btn_file_lwop").click(async function () {
            clearForm();
            setLwopMode(true);
            await init_leave();           // refresh balances & populate LEAVE_TYPES
            addRow();
            resetDetailsSection();
            refreshCommutationVisibility();
            file_leave_modal.toggle();
        });
```

- [ ] **Step 4: Skip the client-side balance guard in `handleSaveLeave` when in LWOP mode, and send the flag**

In `handleSaveLeave()`, wrap the existing balance-guard block (lines 395-412) in an `if` check:

```javascript
        // Guard: ensure hours filed per leave type do not exceed available balance.
        // Skipped entirely in LWOP mode — filing without credits is the whole point.
        const isLwop = $('#is_lwop_flag').val() === '1';
        if (!isLwop) {
            const hoursPerType = {};
            date_applied_array.forEach(d => {
                if (!d.leave_type_id) return;
                hoursPerType[d.leave_type_id] = (hoursPerType[d.leave_type_id] || 0) + parseInt(d.hours, 10);
            });
            for (const [typeId, totalHours] of Object.entries(hoursPerType)) {
                const lt = LEAVE_TYPES.find(t => t.id === parseInt(typeId, 10));
                if (!lt) continue;
                const availableHours = parseFloat(lt.balance) * 8 / 1.25;
                if (totalHours > availableHours) {
                    is_valid = false;
                    toastr.error(
                        `Insufficient ${lt.description} credits. You have ${availableHours.toFixed(2)} hrs available but are filing ${totalHours} hrs.`,
                        "Insufficient Balance"
                    );
                }
            }
        }
```

Then, in the same function, add `is_lwop: isLwop` to the `params` object (around line 416-421):

```javascript
        const params = {
            details_map,
            commutation,
            dates: date_applied_array,  // each item has { date_applied, hours, leave_type_id }
            applicant_signature: applicantSignature,
            is_lwop: isLwop,
        };
```

- [ ] **Step 5: Set LWOP mode when editing an LWOP application**

In `editApplication(id)`, right after `await init_leave();` (around line 936), add:

```javascript
            setLwopMode(!!data.is_lwop);
```

- [ ] **Step 6: Toggle the LWOP badge in `viewApplication`**

In `viewApplication(id)`, right after `$('#leave_commutation').text(data.commutation);` (around line 1027), add:

```javascript
            $('#lwop_badge').toggleClass('d-none', !data.is_lwop);
```

- [ ] **Step 7: Add the LWOP history block**

At the very end of the file, right before the closing `</script>` tag (after the `$('#tab-monetizations').on('shown.bs.tab', ...)` block, line 1401), add:

```javascript

    /** ================================================
     *  LWOP APPLICATIONS
     * ================================================ */
    let lwop_page      = 1;
    let lwop_last_page = 1;

    async function getLwopHistory() {
        $("#lwop_history_list").html("<tr><td colspan='4'>Loading data, please wait...</td></tr>");
        $("#lwop_pagination_links").empty();
        $("#lwop_page_indicator").text("");

        try {
            const payload = await fetchData(`${ROUTE_GET_LEAVES}?page=${lwop_page}&is_lwop=1`);
            const data       = payload.results    || [];
            const pagination = payload.pagination || null;

            $("#lwop_history_list").empty();
            $("#lwop_pagination_links").empty();
            $("#lwop_page_indicator").text("");

            if (!data.length) {
                $("#lwop_pagination_container").addClass("d-none");
                $("#lwop_history_list").html("<tr><td colspan='4'>No record found</td></tr>");
                return;
            }

            $("#lwop_pagination_container").removeClass("d-none");
            let max_visible = data.length;

            if (pagination) {
                lwop_page      = pagination.current_page;
                lwop_last_page = pagination.last_page;
                $("#lwop_page_items_count").text(`${pagination.from} - ${pagination.to} of ${pagination.total}`);
                $("#lwop_page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
                renderLwopPaginationLinks(pagination);
            } else {
                $("#lwop_page_items_count").text(`${((lwop_page - 1) * 10) + 1} - ${((lwop_page - 1) * 10) + max_visible}`);
                $("#lwop_page_indicator").text(`Page ${lwop_page}`);
            }

            data.slice(0, max_visible).forEach(it => {
                const dates  = Object.values(it.dates_applied).map(d => moment(d.date_applied).format("MM/DD/YYYY"));
                const status = it.status === "Approved" ? `<span class="badge bg-success">APPROVED</span>`
                    : it.status === "Rejected" ? `<span class="badge bg-danger">REJECTED</span>`
                        : `<span class="badge bg-secondary">PENDING</span>`;

                const editItem = it.is_editable
                    ? `<li><a class="dropdown-item" href="javascript:void(0)" onclick="editApplication(${it.id})"><i class="bi bi-pencil-fill text-warning me-2"></i> Edit</a></li>`
                    : '';
                const deleteItem = it.is_deletable
                    ? `<li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="cancelApplication(${it.id})"><i class="bi bi-trash-fill me-2"></i> Delete</a></li>`
                    : '';

                $("#lwop_history_list").append(`
                <tr id="lwop_id_${it.id}">
                    <td>${it.description}</td>
                    <td>${dates.join(", ")}</td>
                    <td>${status}</td>
                    <td>
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: transparent">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu">
                                ${editItem}
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewApplication(${it.id})">
                                        <i class="bi bi-eye-fill text-success me-2"></i> View
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="printApplication(${it.id})">
                                        <i class="bi bi-printer text-primary me-2"></i> Print
                                    </a>
                                </li>
                                ${deleteItem}
                            </ul>
                        </div>
                    </td>
                </tr>
                `);
            });
        } catch {
            $("#lwop_history_list").html("<tr><td colspan='4'>Error loading data</td></tr>");
        }
    }

    function renderLwopPaginationLinks(pagination) {
        const $links = $("#lwop_pagination_links");
        if (!$links.length || !pagination || !pagination.last_page) return;

        const current = pagination.current_page;
        const last    = pagination.last_page;
        const start   = Math.max(1, current - 2);
        const end     = Math.min(last, current + 2);

        $links.empty();
        $links.append("<li class='page-item" + (current <= 1 ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current - 1) + "'>Prev</a></li>");

        if (start > 1) {
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='1'>1</a></li>");
            if (start > 2) $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
        }

        for (let i = start; i <= end; i++) {
            $links.append("<li class='page-item" + (i === current ? " active" : "") + "'><a class='page-link' href='#' data-page='" + i + "'>" + i + "</a></li>");
        }

        if (end < last) {
            if (end < last - 1) $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='" + last + "'>" + last + "</a></li>");
        }

        $links.append("<li class='page-item" + (current >= last ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current + 1) + "'>Next</a></li>");
    }

    $("body").on("click", "#lwop_pagination_links .page-link[data-page]", function (e) {
        e.preventDefault();
        const targetPage = parseInt($(this).attr("data-page"), 10);
        if (!targetPage || targetPage === lwop_page) return;
        if (targetPage < 1 || targetPage > lwop_last_page) return;
        lwop_page = targetPage;
        getLwopHistory();
    });

    // Load LWOP list when tab is activated
    $('#tab-lwop').on('shown.bs.tab', function () {
        lwop_page = 1;
        getLwopHistory();
    });
```

- [ ] **Step 8: Verify in browser**

Run: open `/leave_application`, click the "LWOP" tab (should lazy-load an empty or populated list depending on prior tinker inserts), click "File LWOP", pick a leave type that shows `0.000` balance in the credit panel, add a date, fill required fields, sign, and submit.
Expected: submission succeeds with a success toast, the new row appears in the LWOP tab's history list (not in the Leave Applications tab's list), and `leave_credits.based_credit` for that type is confirmed unchanged via `php artisan tinker --execute="echo DB::table('leave_credits')->where('user_id', <id>)->where('leave_id', <type_id>)->value('based_credit');"` before and after.

- [ ] **Step 9: Commit**

```bash
git add resources/views/js/leave_application.blade.php
git commit -m "feat: wire LWOP filing mode, unfiltered leave-type dropdown, and LWOP history tab"
```

---

### Task 8: LWOP badge on the approval queue list

**Files:**
- Modify: `resources/views/js/leave_approval.blade.php:146-153` (`renderLeaves`)

- [ ] **Step 1: Add the badge to the description cell**

Replace:

```javascript
        data.forEach(it => $("#leave_approval_list").append(`
        <tr>
            <td>${it.lname}, ${it.fname} ${it.mname ?? ""}</td>
            <td>${it.description} ${it.is_lwop ? '<span class="badge bg-warning text-dark ms-1">LWOP</span>' : ''}</td>
            <td>${moment(it.created_at).format("MM/DD/YYYY hh:mm:ss")}</td>
            <td><button class="btn btn-sm leave-view-btn" onclick="viewApplication(${it.id}, this)">${leaveApprovalViewButtonHtml}</button></td>
        </tr>
    `));
```

- [ ] **Step 2: Verify in browser**

Run: log in as an approver (SH/DH/HR) whose queue includes the LWOP application filed in Task 7, open `/leave_approval`.
Expected: that row shows a yellow "LWOP" badge next to the leave type description; opening it in the view modal shows the same badge (from Task 6/7's `#lwop_badge` wiring) next to "Type:".

- [ ] **Step 3: Commit**

```bash
git add resources/views/js/leave_approval.blade.php
git commit -m "feat: show LWOP badge in the approval queue list"
```

---

### Task 9: End-to-end manual verification

**Files:** none (verification only)

- [ ] **Step 1: Full happy-path walkthrough**

As a regular employee (in browser, logged in):
1. Go to `/leave_application`, confirm three tabs render: Leave Applications, Monetizations, LWOP.
2. Note current credit balance for a leave type (ideally `0.000` for a clean test, e.g. via `php artisan tinker` setting `leave_credits.based_credit = 0` for that user/type first, remembering to note the original value to restore afterward).
3. Click "LWOP" tab → "File LWOP", pick that leave type (it must appear in the dropdown even at 0 balance — confirms Task 7 Step 1), fill in the form, sign, submit.
4. Confirm success toast, confirm the entry appears in the LWOP tab's history and NOT in the Leave Applications tab's history.
5. Run `php artisan tinker --execute="echo DB::table('leave_credits')->where(...)->value('based_credit');"` — confirm unchanged.

- [ ] **Step 2: Approval walkthrough**

As the SH/DH/HR approver for that employee, open `/leave_approval`, confirm the LWOP badge appears, open it, approve through each stage, confirm it reaches "Approved" status without ever touching `leave_credits`.

- [ ] **Step 3: Rejection walkthrough**

File a second LWOP application, have the SH reject it. Confirm `leave_applications.status = 'Rejected'` and `leave_credits.based_credit` is still unchanged (proves the no-op restore behavior noted in Task 2).

- [ ] **Step 4: Regression check on regular filing**

File one regular (non-LWOP) leave application for a type that DOES have available balance, via the "Leave Applications" tab. Confirm the credit check and deduction still work exactly as before (submit succeeds, balance decreases by the expected amount). Then attempt to file one for a type with **zero** balance via the same (non-LWOP) tab and confirm it is still correctly rejected with the "Insufficient credits" error — proving Task 2's `if (!$isLwop)` guard didn't accidentally disable the check for regular filings.

- [ ] **Step 5: Clean up test data**

Delete/cancel the test LWOP applications created above via the UI (or `php artisan tinker`) if this was done against real employee data, and restore any manually-zeroed `leave_credits.based_credit` value from Step 1.
