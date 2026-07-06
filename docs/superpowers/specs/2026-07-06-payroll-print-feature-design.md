# Payroll Print Feature — Design

**Goal:** Add two printable PDF outputs to the Payroll module: (1) an itemized payslip matching the agency's real government payslip format, and (2) a condensed payroll summary/roster for reviewing an entire payroll at a glance.

**Context:** The Payroll module's DB schema and full create → compute → approve → release lifecycle were verified working earlier in this effort (see `2026-06-08-payroll-cutoff-prerequisite-design.md` for the cutoff_id prerequisite work, and this session's live verification against a real payslip for employee 1116 / Pangcatan). A `payslip()` route and `pdf/payroll_payslip.blade.php` view already exist but render a generic, non-itemized placeholder. There is no payroll summary/roster print at all today (only an Excel export, `exportRegularPayroll()`).

**Reference document:** The user supplied a real payslip PDF (Cebu South Medical Center, DOH) as the exact target format for the itemized payslip. A mockup matching this format (using the existing `public/img/letter_head_banner.png` letterhead asset in place of the reference's 3-logo header, since those individual logo image files don't exist in this codebase) was reviewed and approved during brainstorming.

## Explicitly out of scope

- **PhilHealth Share sub-slip** and **Monetization of Leave Balance sub-slip** — both appear in the reference document but neither has a backing feature in this codebase. PhilHealth professional-fee sharing doesn't exist anywhere in the app. Leave Monetization (`leave_monetizations` table) only stores `days_requested`/`credits_deducted` — the peso payout shown in the reference (via a "constant factor" calculation) is computed manually by HR outside the system today. Building either would mean designing a new feature from scratch, not just formatting existing data. Deferred to a future request.

## 1. Payslip PDF (upgrade existing route)

No new controller method or route — `PayrollController::payslip($payroll_id)` and its `SnappyPdf::loadView('pdf.payroll_payslip', ...)` call already exist and already pass `$payroll` and `$employees` (from `PayrollServices::getPayrollEmployee($payroll_id)`).

**Only `resources/views/pdf/payroll_payslip.blade.php` changes.** Its content is replaced with the itemized layout:
- Header: `letter_head_banner.png`, "Republic of the Philippines / Department of Health / CEBU SOUTH MEDICAL CENTER / (Dr. Jacinto Velez Sr. Memorial Hospital) / San Isidro, Talisay City, Cebu 6045, Philippines", payroll title as the page title (e.g. "JUNE 2026 PAYSLIP").
- Employee block: Name, Designation, Salary Grade (`salary_grade`-`step`). Confirmed via code trace: `getPayrollEmployee()` does not currently join `payroll_settings`, so it must gain a `leftJoin('hris.payroll_settings as ps', 'ps.userid', '=', 'e.employee_id')` and select `ps.salary_grade`, `ps.step`.
- GROSS PAY table: Basic Pay, Basic Pay Adjustment, Less: Leave w/o pay, Final Basic Pay, PERA, Subsistence, Laundry, Hazard, Longevity, TOTAL GROSS PAY. All lines always print; zero/null renders as `-`.
- DEDUCTIONS table, three subsections exactly as the reference: **Mandatory Deductions** (Withholding Tax, Withholding Tax Adjustment, GSIS, Pag-IBIG, PhilHealth), **Government Loans** (all GSIS loan variants + Pag-IBIG variants, every line always printed), **Other Deductions** (CFI Coop, 1st Valley Bank, TDHEA, DBP, UCPB, SSS deduction/adjustment, GSIS adjustment/LWOP, allowance adjustments, subsistence-deduction-from-leave). TOTAL DEDUCTION sums all of the above.
- NET PAY block: Net Pay, 1st Half (10th), 2nd Half (25th), generated-at timestamp.
- One payslip per employee, page-break between employees (unchanged from current behavior).

All values are already present on `getPayrollEmployee()`'s result via `getLegacyComponentMap()` — no new queries needed for this piece.

## 2. Payroll Summary PDF (new)

**Route:** `GET /payroll/summary/{payroll_id}`, name `payrollSummary`.

**Controller:** New `PayrollController::payrollSummary($payroll_id)` method, mirroring the shape of `payslip()`: fetch `$payroll` via `getPayrollPeriod()`, `$employees` via `getPayrollEmployee()` (same data source, no new query), render via `SnappyPdf::loadView('pdf.payroll_summary', ...)`, landscape orientation (this is a wide roster table, unlike the portrait payslip), download as `summary_{safe_title}.pdf`.

**View:** New `resources/views/pdf/payroll_summary.blade.php`:
- Same letterhead header, payroll title, period.
- One row per employee: Name, Designation, Basic Pay, Total Allowances (`other_earnings`), Total Deductions (`deductions`), Net Pay.
- Totals row at the bottom summing Basic Pay, Total Allowances, Total Deductions, Net Pay across all employees.

## 3. UI wiring

`resources/views/payroll.blade.php`: add a "Print Summary" entry to the existing per-row actions dropdown (`payroll.blade.php:803`, next to "Download Payslip"), calling a new `downloadSummary(id)` JS function (same pattern as `downloadPayslip(id)` at line 603 — direct `window.location.href` navigation, no fetch/AJAX needed since it's a file download).

Gating: reuse the existing `can_generate_payslip` flag (`status in [approved, released]`, computed in `PayrollController.php:557`) for the new button rather than introducing a duplicate `can_print_summary` flag — the availability rule is identical for both prints.

## 4. Testing

Extend `tests/Feature/PayrollCutoffTest.php` (or add a small new test file if it grows unwieldy) with an HTTP-level test: build an approved payroll with one computed employee (reusing the existing lifecycle-test helpers), then assert both `GET /payroll/payslip/{id}` and `GET /payroll/summary/{id}` return HTTP 200 with `Content-Type: application/pdf`. This confirms the routes render without error; exact visual layout was already validated via the approved mockup and is not re-asserted in the test (no PDF-content parsing).

## File map

| Action | File |
|---|---|
| Modify | `app/Services/PayrollServices.php` (join `payroll_settings` into `getPayrollEmployee()` for salary grade/step) |
| Modify | `resources/views/pdf/payroll_payslip.blade.php` |
| Modify | `app/Http/Controllers/PayrollController.php` (new `payrollSummary()` method) |
| Modify | `routes/web.php` (new route) |
| Create | `resources/views/pdf/payroll_summary.blade.php` |
| Modify | `resources/views/payroll.blade.php` (new dropdown action + JS function) |
| Modify | `tests/Feature/PayrollCutoffTest.php` |
