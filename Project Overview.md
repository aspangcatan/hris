# Project Overview

## Summary
This repository is a Laravel-based HRIS (Human Resource Information System) that manages employee records and HR workflows like leave, DTR (daily time record), schedules, payroll, SALN, and related reporting/printing.

## Tech Stack
- Backend: PHP 7.3+ (platform pinned to 7.4.33) with Laravel 8.0 (`composer.json`).
- Frontend build: Laravel Mix (Webpack) via `package.json` scripts.
- PDF/Report generation: Dompdf, Snappy (wkhtmltopdf wrapper), FPDF/FPDI, PHPSpreadsheet (`composer.json`).

## Major Features and Domains
Derived from controllers, models, and routes in `routes/web.php` and `app/Http/Controllers`:
- Authentication and user profile access (session-based, `auth` middleware group in `routes/web.php`).
- Employee master data: personal info, address, family background, education, civil service, work experience, voluntary work, learning & development, references, other info.
- Leave management: leave types, balances, leave applications, approvals, and credit management.
- DTR (Daily Time Record): logging, manual entries, printing, and mobile DTR endpoints.
- Schedule management: master list, per-employee schedules, printing.
- Payroll: settings, payroll runs, approvals, release, exports, payslips.
- SALN (Statement of Assets, Liabilities, and Net Worth): filing, historical tracking, admin review, summary reporting.
- Hospital orders and overtime filings with approval flows.
- Pass slip filing and approver workflows.
- Reports: leave balance and other printable outputs.

## Key Entry Points
- Web routes: `routes/web.php` (primary application routes, including mobile DTR endpoints and APK download links).
- API routes: `routes/api.php` (default scaffolded endpoint only).

## Data Model Overview
Models found under `app/Models` indicate core entities:
- Employee, User, Address, FamilyBackground, Education, CivilService, Work, Voluntary, Learning, Reference, Skill, Recognition, Membership.
- Leave-related: LeaveApplication, LeaveCredits, LeaveCreditLog, LeaveBalanceReport, LeaveFile, LeaveApplicationTrack.
- DTR/Schedule: (DTR appears controller-driven), Schedule-related logic in `ScheduleController`.
- Payroll, PromotionWork, ServiceRecords, Survey, HospitalOrder, PassSlip.
- SALN-related: Declarant, Spouse, Children, Real/Personal Properties, Liabilities, BusinessInterest, GovernmentRelative, Marriages, plus spouse variants.

## UI and Views
- Blade templates in `resources/views` for core screens (dashboard, employee, leave, DTR, payroll, schedules, SALN).
- Modularized forms and modals in `resources/views/forms` and `resources/views/modals`.
- PDF templates in `resources/views/pdf` and `resources/views/pdf/new` used for print-ready outputs.
- Additional JS view partials in `resources/views/js`.

## Documents and Exports
- Print controllers and PDF views for PDS, service records, SALN, DTR, leave forms, payslips, schedules, and reports.
- Spreadsheet generation likely through `phpoffice/phpspreadsheet` and PDF libraries (see `composer.json`).

## Mobile DTR
- Mobile DTR endpoints are exposed in `routes/web.php` (e.g., `/mobile/login`, `/dtr/store`).
- APK files are served from `public/apk/*` via download routes in `routes/web.php`.

## Testing
- PHPUnit configured via `phpunit.xml` with dependencies in `composer.json`.

## Notes / Gaps
- `README.md` is still the default Laravel scaffold and does not describe this HRIS.
- `DESIGN_SYSTEM.md` is referenced in your IDE tabs but does not exist in the repo root.

## Suggested Next Steps (Optional)
- Replace `README.md` with project-specific setup, environment, and workflow docs.
- Add/restore a `DESIGN_SYSTEM.md` if a UI system exists.
- Document environment variables and required services (DB, queue, mail).
