# Service Record Excel Upload — Design

**Date:** 2026-11-06
**Template:** `public/pdf/SERVICE_RECORD_TEMPLATE.xlsx`

## Goal
From the Service Records tab of the Employee Information modal, HR uploads an
employee's service-record Excel file, reviews/edits each parsed row as a card in
a review modal, and saves all rows with one "Confirm All".

## Entry point
- **Upload** button beside Print / New in `forms/service_record.blade.php`.
- Opens a hidden file input (`.xlsx,.xls`); the file is posted with
  `user_id = employee_id` to `POST /service_records/parse`.

## Parsing (`App\Services\ServiceRecordExcelParser`)
- First sheet only. Data starts at row 13; stops at the first row whose column H
  is blank or not a date (the "Issued in compliance…" footer).
- Columns: H From, I To, J Designation, K Status, L Salary, M Office,
  N Leave w/o pay, O Separation. P and beyond ignored.
- Dates accepted as Excel serials or text `m/d/Y` (also `Y-m-d`). Output `Y-m-d`.
- To = `Present` (case-insensitive) → `current_work = 1`, `date_to = null`.
- Salary: numeric value; text with commas is stripped to a number.
- Unparseable date → row still returned, with `errors[field]` message.
- Name: I3 (surname), K3 (given), N3 (middle). Compared case/whitespace-insensitively
  against `tdh_user.users` lname/fname/mname → `name_mismatch` bool plus both names.
- Nothing is saved.

Response: `{code, file_name, name_mismatch, file_name_text, employee_name_text, rows: [...]}`.

## Review modal (`modals/service_record_upload.blade.php`, `#service_upload_modal`)
- XL `premium-modal`. Header shows file name + row count; warning banner on name mismatch.
- One card per row: row number, editable From / To / Present switch / Designation /
  Status / Salary / Office / Leave w/o pay / Separation, remove button.
- Footer: Cancel, **Confirm All (N)**. Disabled while any card has a missing required
  field (From, To unless Present, Designation, Status, Salary, Office) or no cards remain.

## Save (`POST /service_records/bulk_store`)
- Body `{user_id, rows: [...]}`. Appends blindly (no duplicate check).
- Validates every row first (same required fields); any failure → 422-style
  `{code: 500, message, errors}` and nothing is inserted (table is MyISAM, no transactions).
- Per row same rules as single save: Present → `date_to = date_from`.
- Success → modal closes, Service Records list reloads.

## Testing
- Parser test against the template: 5 rows, mixed date formats, Present, footer stop,
  salary numbers, name mismatch vs employee 1116.
- Controller tests for `parseUpload` (UploadedFile) and `bulkStore`
  (insert, Present rule, all-or-nothing validation). Cleanup by id like existing test.
