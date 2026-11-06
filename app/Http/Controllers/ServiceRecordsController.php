<?php

namespace App\Http\Controllers;

use App\Models\ServiceRecords;
use App\Services\ServiceRecordExcelParser;
use Illuminate\Http\Request;

class ServiceRecordsController extends Controller
{
    //
    public function getServiceRecords(Request $request)
    {
        try {
            $service_records = ServiceRecords::where('user_id', $request->id)->get();
            return response(['code' => 200, 'results' => $service_records]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateServiceRecords(Request $request)
    {
        try {
            $service_records = ServiceRecords::find($request->id);
            if (!$service_records) {
                $service_records = new ServiceRecords;
                $service_records->user_id = $request->user_id;
            }
            $is_present = (int) $request->current_work === 1;
            $service_records->date_from = $request->date_from;
            // date_to is NOT NULL; for a present record, mirror date_from (same convention as work experience).
            $service_records->date_to = $is_present ? $request->date_from : $request->date_to;
            $service_records->current_work = $is_present ? 1 : 0;
            $service_records->designation = $request->designation;
            $service_records->appointment_status = $request->appointment_status;
            $service_records->monthly_salary = $request->monthly_salary;
            $service_records->office_assignment = $request->office_assignment;
            $service_records->leave_absence = $request->leave_absence;
            $service_records->separation = $request->separation;
            $service_records->save();
            return response(['code' => 200, 'message' => 'Service records updated', 'id' => $service_records->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /** Reads an uploaded service record workbook and returns its rows for review. Saves nothing. */
    public function parseUpload(Request $request)
    {
        try {
            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                return response(['code' => 500, 'message' => 'Please choose an Excel file to upload.']);
            }
            if (!in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'])) {
                return response(['code' => 500, 'message' => 'Only .xlsx or .xls files are accepted.']);
            }

            $result = app(ServiceRecordExcelParser::class)->parse($file->getRealPath(), (int) $request->user_id);
            if (count($result['rows']) === 0) {
                return response(['code' => 500, 'message' => 'No service record rows found. Data is expected to start at row 13 (columns H to O).']);
            }

            return response(array_merge(['code' => 200, 'file_name' => $file->getClientOriginalName()], $result));
        } catch (\Throwable $exception) {
            return response(['code' => 500, 'message' => 'Could not read the file: ' . $exception->getMessage()]);
        }
    }

    /** Saves all reviewed upload rows for one employee. Validates every row first, then inserts (table is MyISAM). */
    public function bulkStore(Request $request)
    {
        try {
            $userId = (int) $request->user_id;
            $rows = $request->input('rows', []);
            if ($userId <= 0 || !is_array($rows) || count($rows) === 0) {
                return response(['code' => 500, 'message' => 'Nothing to save.']);
            }

            $errors = [];
            foreach ($rows as $i => $row) {
                $rowErrors = $this->validateUploadRow($row);
                if ($rowErrors) {
                    $errors[$i] = $rowErrors;
                }
            }
            if ($errors) {
                return response(['code' => 500, 'message' => 'Some rows are incomplete. Please fix the highlighted fields.', 'errors' => $errors]);
            }

            foreach ($rows as $row) {
                $isPresent = (int) ($row['current_work'] ?? 0) === 1;
                $record = new ServiceRecords;
                $record->user_id = $userId;
                $record->date_from = $row['date_from'];
                $record->date_to = $isPresent ? $row['date_from'] : $row['date_to'];
                $record->current_work = $isPresent ? 1 : 0;
                $record->designation = trim($row['designation']);
                $record->appointment_status = trim($row['appointment_status']);
                $record->monthly_salary = trim((string) $row['monthly_salary']);
                $record->office_assignment = trim($row['office_assignment']);
                $record->leave_absence = trim((string) ($row['leave_absence'] ?? ''));
                $record->separation = trim((string) ($row['separation'] ?? ''));
                $record->save();
            }

            return response(['code' => 200, 'message' => count($rows) . ' service record(s) saved']);
        } catch (\Throwable $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    private function validateUploadRow($row): array
    {
        if (!is_array($row)) {
            return ['row' => 'Invalid row'];
        }
        $errors = [];
        $isDate = function ($v) {
            $d = \DateTime::createFromFormat('!Y-m-d', (string) $v);
            return $d && $d->format('Y-m-d') === $v;
        };
        if (!$isDate($row['date_from'] ?? null)) {
            $errors['date_from'] = 'From date is required';
        }
        if ((int) ($row['current_work'] ?? 0) !== 1 && !$isDate($row['date_to'] ?? null)) {
            $errors['date_to'] = 'To date is required unless Present';
        }
        foreach (['designation' => 'Designation', 'appointment_status' => 'Status', 'monthly_salary' => 'Salary', 'office_assignment' => 'Office'] as $field => $label) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                $errors[$field] = $label . ' is required';
            }
        }
        return $errors;
    }

    public function deleteServiceRecords(Request $request)
    {
        try {
            $service_records = ServiceRecords::find($request->id);
            if (!$service_records) {
                return response(['code' => 500, 'message' => 'Service record ID does not exists']);
            }
            $service_records->forceDelete();
            return response(['code' => 200, 'message' => 'Service record deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
