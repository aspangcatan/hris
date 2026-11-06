<?php

namespace App\Services;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads a service record workbook laid out like public/pdf/SERVICE_RECORD_TEMPLATE.xlsx:
 * name on row 3 (I/K/N), data rows from H13 to O, ending at the first row whose
 * column H is not a date (the "Issued in compliance…" footer).
 */
class ServiceRecordExcelParser
{
    public const FIRST_DATA_ROW = 13;

    private const MAX_ROWS = 500;

    private const COLUMNS = [
        'H' => 'date_from',
        'I' => 'date_to',
        'J' => 'designation',
        'K' => 'appointment_status',
        'L' => 'monthly_salary',
        'M' => 'office_assignment',
        'N' => 'leave_absence',
        'O' => 'separation',
    ];

    public function parse(string $path, int $userId): array
    {
        $sheet = IOFactory::load($path)->getSheet(0);

        $rows = [];
        for ($r = self::FIRST_DATA_ROW; $r < self::FIRST_DATA_ROW + self::MAX_ROWS; $r++) {
            if ($this->toDate($this->cell($sheet, 'H' . $r)) === null) {
                break;
            }
            $rows[] = $this->parseRow($sheet, $r);
        }

        $fileName = [
            $this->cellText($sheet, 'I3'),
            $this->cellText($sheet, 'K3'),
            $this->cellText($sheet, 'N3'),
        ];
        $user = User::find($userId);
        $employeeName = $user ? [$user->lname, $user->fname, $user->mname] : ['', '', ''];

        return [
            'rows' => $rows,
            'name_mismatch' => $this->normalizeName($fileName) !== $this->normalizeName($employeeName),
            'file_name_text' => $this->nameText($fileName),
            'employee_name_text' => $this->nameText($employeeName),
        ];
    }

    private function parseRow(Worksheet $sheet, int $r): array
    {
        $row = ['row' => $r, 'current_work' => 0, 'errors' => []];

        foreach (self::COLUMNS as $col => $field) {
            $raw = $this->cell($sheet, $col . $r);
            switch ($field) {
                case 'date_from':
                    $row[$field] = $this->toDate($raw);
                    break;
                case 'date_to':
                    $text = trim((string) $raw);
                    $row[$field] = null;
                    if (strcasecmp($text, 'present') === 0) {
                        $row['current_work'] = 1;
                    } elseif ($text === '') {
                        $row['errors'][$field] = 'To date is missing';
                    } elseif (($date = $this->toDate($raw)) !== null) {
                        $row[$field] = $date;
                    } else {
                        $row['errors'][$field] = 'Unrecognized date "' . $text . '"';
                    }
                    break;
                case 'monthly_salary':
                    $row[$field] = $this->toAmount($raw);
                    break;
                default:
                    $row[$field] = trim((string) $raw);
            }
        }

        return $row;
    }

    private function cell(Worksheet $sheet, string $coord)
    {
        $cell = $sheet->getCell($coord);
        try {
            $value = $cell->getCalculatedValue();
        } catch (\Throwable $e) {
            $value = $cell->getValue();
        }
        return is_object($value) ? (string) $value : $value;
    }

    private function cellText(Worksheet $sheet, string $coord): string
    {
        return trim((string) $this->cell($sheet, $coord));
    }

    /** Excel serial or m/d/Y-style text → Y-m-d, else null. */
    private function toDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $serial = (float) $value;
            // Plausible Excel serial range: 1900-01-01 .. 2100-12-31
            if ($serial < 1 || $serial > 73415) {
                return null;
            }
            return ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
        }

        $text = trim((string) $value);
        foreach (['!m/d/Y', '!n/j/Y', '!m/d/y', '!n/j/y', '!Y-m-d', '!m-d-Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $text);
            $errors = \DateTime::getLastErrors();
            if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }

    private function toAmount($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $clean = is_numeric($value) ? $value : str_replace([',', ' ', '₱'], '', (string) $value);
        if (!is_numeric($clean)) {
            return trim((string) $value);
        }
        $number = (float) $clean;
        return floor($number) == $number ? (string) (int) $number : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function normalizeName(array $parts): string
    {
        return implode('|', array_map(function ($p) {
            return strtoupper(preg_replace('/\s+/', ' ', trim((string) $p)));
        }, $parts));
    }

    private function nameText(array $parts): string
    {
        [$last, $first, $middle] = array_map(function ($p) {
            return strtoupper(preg_replace('/\s+/', ' ', trim((string) $p)));
        }, $parts);
        return trim($last . ', ' . trim($first . ' ' . $middle), ', ');
    }
}
