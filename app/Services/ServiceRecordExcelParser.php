<?php

namespace App\Services;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads a service record workbook laid out like public/pdf/SERVICE_RECORD_TEMPLATE.xlsx:
 * name on row 3 (I/K/N), data rows from H13 to O by default. The caller may pass a
 * different first-row span (e.g. B20:I20); the 8 fields map left-to-right from the start
 * column, and reading moves down one row at a time until the first column is not a date
 * (the "Issued in compliance…" footer).
 */
class ServiceRecordExcelParser
{
    public const FIRST_DATA_ROW = 13;

    public const FIRST_COLUMN = 'H';

    public const LAST_COLUMN = 'O';

    private const MAX_ROWS = 500;

    /** Field order, left to right, starting at the first column of the range. */
    public const FIELDS = [
        'date_from',
        'date_to',
        'designation',
        'appointment_status',
        'monthly_salary',
        'office_assignment',
        'leave_absence',
        'separation',
    ];

    /**
     * Returns an error message when the first-row span is not usable, else null.
     * The span must be a single row covering exactly one column per field.
     */
    public static function rangeError($startCol, $startRow, $endCol, $endRow): ?string
    {
        foreach ([$startCol, $endCol] as $col) {
            if (!is_string($col) || !preg_match('/^[A-Za-z]{1,3}$/', trim($col))) {
                return 'Columns must be letters only (e.g. H).';
            }
        }
        foreach ([$startRow, $endRow] as $row) {
            if (!is_numeric($row) || (int) $row != $row || (int) $row < 1) {
                return 'Rows must be whole numbers starting at 1.';
            }
        }
        if ((int) $startRow !== (int) $endRow) {
            return 'Starting and ending row must be the same — the range describes the first data row.';
        }
        $span = Coordinate::columnIndexFromString(strtoupper(trim($endCol))) - Coordinate::columnIndexFromString(strtoupper(trim($startCol))) + 1;
        if ($span !== count(self::FIELDS)) {
            return 'The range must cover exactly ' . count(self::FIELDS) . ' columns (From, To, Designation, Status, Salary, Office, Leave, Separation); '
                . strtoupper(trim($startCol)) . ' to ' . strtoupper(trim($endCol)) . ' covers ' . $span . '.';
        }
        return null;
    }

    public function parse(
        string $path,
        int $userId,
        string $startCol = self::FIRST_COLUMN,
        int $startRow = self::FIRST_DATA_ROW,
        string $endCol = self::LAST_COLUMN,
        int $endRow = self::FIRST_DATA_ROW
    ): array {
        if ($error = self::rangeError($startCol, $startRow, $endCol, $endRow)) {
            throw new \InvalidArgumentException($error);
        }
        $sheet = IOFactory::load($path)->getSheet(0);

        $startIndex = Coordinate::columnIndexFromString(strtoupper(trim($startCol)));
        $columns = [];
        foreach (self::FIELDS as $i => $field) {
            $columns[Coordinate::stringFromColumnIndex($startIndex + $i)] = $field;
        }
        $firstCol = array_key_first($columns);

        $rows = [];
        for ($r = $startRow; $r < $startRow + self::MAX_ROWS; $r++) {
            if ($this->toDate($this->cell($sheet, $firstCol . $r)) === null) {
                break;
            }
            $rows[] = $this->parseRow($sheet, $r, $columns);
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

    private function parseRow(Worksheet $sheet, int $r, array $columns): array
    {
        $row = ['row' => $r, 'current_work' => 0, 'errors' => []];

        foreach ($columns as $col => $field) {
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
