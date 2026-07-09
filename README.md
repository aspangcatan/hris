# HRIS (Human Resource Information System)

## Overview
This repository contains a Laravel 8 HRIS that manages employee records and HR workflows such as leave, DTR (daily time record), schedules, payroll, SALN, and related reporting/printing. The app also exposes mobile DTR endpoints and serves APK downloads.

## Key Features
- Employee master data and PDS management
- Leave applications, approvals, and credit management
- DTR logging, manual entries, and printing
- Schedule management and printing
- Payroll runs, approvals, release, exports, and payslips
- SALN filing, historical tracking, and admin review
- Hospital orders, overtime requests, and pass slip workflows
- PDF and spreadsheet exports for reporting
- Mobile DTR endpoints and APK downloads

## Tech Stack
- Backend: PHP 7.3+ (platform pinned to 7.4.33) with Laravel 8
- Frontend: Blade templates, Bootstrap 5, Tailwind CDN utilities
- Build: Laravel Mix (Webpack)
- Reporting: Dompdf, Snappy (wkhtmltopdf), FPDF/FPDI, PHPSpreadsheet
- UI libraries: Bootstrap Icons, Font Awesome, Simple Datatables, Select2, Toastr

## Requirements
- PHP 7.4.33 (per Composer platform config)
- Composer
- Node.js and npm (for asset builds)
- Database configured in `.env`

## Setup
1. Install PHP dependencies:
   `composer install`
2. Install frontend dependencies:
   `npm install`
3. Configure environment:
   - Copy or update `.env` with database credentials and app settings.
   - Generate an app key if needed: `php artisan key:generate`
4. Database:
   - Run migrations and seeders if applicable to your environment.

## Running Locally
- With Laravel's dev server:
  `php artisan serve`
- Or through your local web server (WAMP/LAMP) pointing to `public`.

## Asset Build
- Development build:
  `npm run dev`
- Production build:
  `npm run production`

## Testing
- PHPUnit:
  `vendor/bin/phpunit`

## Key Entry Points
- Web routes: `routes/web.php`
- API routes: `routes/api.php` (default scaffold)
- Main layout: `resources/views/master.blade.php`

## UI Structure
- Layouts: `resources/views/layouts`
- Pages: `resources/views/*.blade.php`
- Form partials: `resources/views/forms`
- Modals: `resources/views/modals`
- View-level scripts: `resources/views/js`
- PDF templates: `resources/views/pdf`

## Mobile DTR
- Endpoints live in `routes/web.php` under the mobile section.
- APKs are served from `public/apk/*` via download routes.

## Notes
- The UI is built on top of the NiceAdmin Bootstrap template with custom theming.
- The design language is primarily defined in `resources/views/master.blade.php` and view-specific styles (for example `resources/views/layouts/saln_styles.blade.php`).

## License
This project is a private application. See `composer.json` for framework licensing details.