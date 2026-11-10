<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollServices
{
    public function getPayroll($id)
    {
        return DB::table('hris.payrolls')->where('id', $id)->first();
    }

    private function formatEmployeeName($item)
    {
        return trim(implode(' ', array_filter([
            strtoupper($item->lname ?? '') . ',',
            strtoupper($item->fname ?? ''),
            !empty($item->mname) ? strtoupper(substr($item->mname, 0, 1)) . '.' : null,
            $item->suffix ?? null,
        ])));
    }

    public function exportRegularPayroll($payrollEmployees, $exportPath, $payroll = null)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Summary');

        // Define column mapping according to your desired order
        $columns = [
            'No.' => null,
            'NAME' => 'name',
            'FAMILY NAME' => 'lname',
            'FIRST NAME' => 'fname',
            'MIDDLE NAME' => 'mname',
            'SUFFIX' => 'suffix',
            'MI' => 'mi',
            'STAT' => 'stat',
            'DIVISION' => 'division',
            'DESIGNATION' => 'designation',
            'zz' => 'zz',
            'SG' => 'sg',
            'Step' => 'step',
            'SG B' => 'sg_b',
            'SSL 6 BASIC PAY' => 'basic_salary',
            'BASIC PAY ADJ' => 'salary_adjustment',
            'BASIC PAY LWOP' => 'late_absent',
            'FINAL BASIC PAY' => 'final_basic_pay',
            'PERA' => 'pera',
            'RATA' => 'rata',
            'SUBSISTENCE' => 'subsistence',
            'LAUNDRY' => 'laundry',
            'HAZARD' => 'hazard',
            'LONGEVITY' => 'longevity',
            'QUARTERS ALLOWANCE' => 'quarters_allowance',
            'CELLPHONE ALLOWANCE' => 'cellphone_allowance',
            'GROSS PAY' => 'gross_pay',
            'WITHHOLDING TAX' => 'withholding_tax',
            'WITHHOLDING TAX (adjustment)' => 'withholding_adjustment',
            'GSIS PS' => 'gsis_ps',
            'GSIS GS' => 'gsis_gs',
            'GSIS EC' => 'gsis_ec',
            'HDMF' => 'hdmf',
            'PHIC' => 'phic',
            'PAGIBIG CAL' => 'pagibig_cal',
            'PAGIBIG MPL' => 'pagibig_mpl',
            'PAG-IBIG MP2' => 'pagibig_mp2',
            'PAG-IBIG MP2 2ND ACCOUNT' => 'pagibig_mp2_2nd',
            'PAG-IBIG MP2 3RD ACCOUNT' => 'pagibig_mp2_3rd',
            'GSIS CONSO LOAN' => 'gsis_consoloan',
            'GSIS EMERGENCY LOAN' => 'gsis_emergency_loan',
            'GSIS EDUCATIONAL LOAN' => 'gsis_educational_loan',
            'GSIS POLICY LOAN' => 'gsis_policy_loan',
            'GSIS OPT_LIFE LOAN' => 'gsis_opt_life_loan',
            'GSIS GFAL' => 'gsis_gfal',
            'GSIS MPL' => 'gsis_mpl',
            'GSIS CPL' => 'gsis_cpl',
            'GSIS MPL Lite' => 'gsis_mpl_lite',
            'CFI COOP' => 'cfi_coop',
            '1ST VALLEY BANK' => 'first_valley_bank',
            'TDHEA' => 'tdhea',
            'DBP LOAN' => 'dbp_loan',
            'UCPB LOAN' => 'ucpb_loan',
            'MEDS PAYABLE' => 'meds_payable',
            'PHIC CONTRI ADJ' => 'phic_contri_adj',
            'PHIC REFUND FROM E.O.' => 'phic_refund_eo',
            'HAZARD PAY ADJ' => 'hazard_adjustment',
            'ADJ PERA' => 'pera_adjustment',
            'ADJ SUBSISTENCE' => 'subsistence_adjustment',
            'ADJ LAUNDRY' => 'laundry_adjustment',
            'SSS DEDUCTION' => 'sss_deduction',
            'SSS ADJUSTMENT' => 'sss_adjustment',
            'GSIS PS LWOP (2%)' => 'gsis_ps_lwop',
            'GSIS GS LWOP (2%)' => 'gsis_gs_lwop',
            'GSIS PLREG REFUND' => 'gsis_plreg_refund',
            'GSIS MPL REFUND' => 'gsis_mpl_refund',
            'GSIS EMERGENCY REFUND' => 'gsis_emergency_refund',
            'GSIS CONSOLOAN REFUND' => 'gsis_consoloan_refund',
            'SUBSISTENCE DEDUCTION FROM LEAVE' => 'subsistence_deduction_leave',
            '2nd half adjustment for resigned employee' => 'resigned_adjustment',
            'TOTAL DEDUCTION' => 'deductions',
            'NET PAY' => 'net_pay',
            '1ST HALF 10TH' => 'first_half_10th',
            '2ND HALF 25TH' => 'second_half_25th',
        ];

        $textColumns = ['NAME', 'FAMILY NAME', 'FIRST NAME', 'MIDDLE NAME', 'SUFFIX', 'MI', 'STAT', 'DIVISION', 'DESIGNATION', 'zz'];
        $plainNumberColumns = ['No.', 'SG', 'Step', 'SG B'];

        $columnCount = count($columns);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

        $titleRow = 1;
        $periodRow = 2;
        $headerRow = 4;
        $dataStartRow = 5;

        $titleText = 'PAYROLL SUMMARY' . (!empty($payroll->title) ? ' - ' . strtoupper($payroll->title) : '');
        $sheet->setCellValue("A{$titleRow}", $titleText);
        $sheet->mergeCells("A{$titleRow}:{$lastColLetter}{$titleRow}");
        $sheet->getStyle("A{$titleRow}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$titleRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        if (!empty($payroll->start_date) && !empty($payroll->end_date)) {
            $periodText = 'For the period of ' . Carbon::parse($payroll->start_date)->format('F j') . ' to ' . Carbon::parse($payroll->end_date)->format('F j, Y');
            $sheet->setCellValue("A{$periodRow}", $periodText);
            $sheet->mergeCells("A{$periodRow}:{$lastColLetter}{$periodRow}");
            $sheet->getStyle("A{$periodRow}")->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle("A{$periodRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        $headerColIndex = 0;
        foreach (array_keys($columns) as $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerColIndex + 1) . $headerRow;
            $sheet->setCellValue($cell, $header);
            $headerColIndex++;
        }

        $row = $dataStartRow;

        foreach ($payrollEmployees as $index => $item) {
            $colIndex = 0;
            foreach ($columns as $header => $field) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . $row;

                if ($field === null) {
                    // Auto number column
                    $sheet->setCellValue($cell, $index + 1);
                } elseif ($header === 'NAME') {
                    $sheet->setCellValue($cell, $this->formatEmployeeName($item));
                } elseif ($header === 'zz') {
                    // Generate acronym for designation
                    $sheet->setCellValue($cell, $this->generateAcronym($item->designation ?? ''));
                } else {
                    $sheet->setCellValue($cell, $item->$field ?? 0);
                }

                $colIndex++;
            }

            $row++;
        }

        $lastDataRow = $row - 1;
        $totalsRow = $row;

        $sheet->setCellValue("A{$totalsRow}", 'TOTAL (' . count($payrollEmployees) . ' employees)');
        $sheet->mergeCells("A{$totalsRow}:J{$totalsRow}");

        $colIndex = 0;
        foreach ($columns as $header => $field) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            if ($colIndex >= 10 && !in_array($header, $textColumns, true) && !in_array($header, $plainNumberColumns, true)) {
                $sheet->setCellValue("{$colLetter}{$totalsRow}", "=SUM({$colLetter}{$dataStartRow}:{$colLetter}{$lastDataRow})");
            }
            $colIndex++;
        }

        // Header styling
        $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        // Totals row styling
        $totalsRange = "A{$totalsRow}:{$lastColLetter}{$totalsRow}";
        $sheet->getStyle($totalsRange)->getFont()->setBold(true);
        $sheet->getStyle($totalsRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');

        // Borders around the whole table (header through totals)
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$totalsRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Per-column alignment, number format, and auto width
        $colIndex = 0;
        foreach ($columns as $header => $field) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $columnRange = "{$colLetter}{$dataStartRow}:{$colLetter}{$lastDataRow}";

            if (in_array($header, $textColumns, true)) {
                $sheet->getStyle($columnRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            } elseif (in_array($header, $plainNumberColumns, true)) {
                $sheet->getStyle($columnRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            } else {
                $sheet->getStyle($columnRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($columnRange)->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            $colIndex++;
        }

        $sheet->freezePane("A{$dataStartRow}");

        // Save to export path
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($exportPath);
    }

    public function exportJobOrderPayroll($payrollEmployees, $exportPath)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Define column mapping according to your new desired order
        $columns = [
            'Serial No.' => null,
            'LAST NAME' => 'lname',
            'FIRST NAME' => 'fname',
            'MIDDLE INITIAL' => 'mi',
            'POSITION' => 'designation',
            'RATE/DAY' => 'rate_per_day',
            '# OF DAYS WORK' => 'days_worked',
            'GROSS AMOUNT' => 'gross_amount',
            'OVERTIME MINUTES' => 'overtime_minutes',
            'AMOUNT (OT)' => 'overtime_amount',
            'TARDINESS MINUTES' => 'tardiness_minutes',
            'AMOUNT (Tardiness)' => 'tardiness_amount',
            'SALARY ADJUSTMENT' => 'salary_adjustment',
            'GROSS AMOUNT EARNED' => 'gross_amount_earned',
            'SSS DEDUCTION' => 'sss_deduction',
            'INCOME TAX (3%)' => 'income_tax',
            '5% TAX DEDUCTION FROM PREVIOUS PAYROLL' => 'prev_payroll_tax_deduction',
            'PERCENTAGE TAX (3%)' => 'percentage_tax',
            'PROFESSIONAL FEE (10%)' => 'professional_fee',
            'TOTAL DEDUCTION' => 'total_deduction',
            'NET AMOUNT DUE' => 'net_amount_due',
        ];

        $headerColIndex = 0;
        foreach (array_keys($columns) as $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerColIndex + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $headerColIndex++;
        }

        $row = 2; // Start from row 2 (headers in row 1)

        foreach ($payrollEmployees as $index => $item) {
            $colIndex = 0;
            foreach ($columns as $header => $field) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . $row;

                if ($field === null) {
                    // Auto number column
                    $sheet->setCellValue($cell, $index + 1);
                } else {
                    $sheet->setCellValue($cell, $item->$field ?? 0);
                }

                $colIndex++;
            }

            $row++;
        }

        // Save to export path
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($exportPath);
    }


    public function getPayrollEmployee($payroll_id)
    {
        $list = DB::table('hris.payroll_employees as e')
            ->join('tdh_user.users as u', 'u.id', '=', 'e.employee_id')
            ->join('tdh_user.designation as de', 'u.designation', '=', 'de.id')
            ->join('tdh_user.division as d', 'd.id', '=', 'u.division')
            ->join('tdh_user.section as s', 's.id', '=', 'u.section')
            ->leftJoin('hris.payroll_settings as ps', 'ps.userid', '=', 'e.employee_id')
            ->where('e.payroll_id', '=', $payroll_id)
            ->select(
                'e.*',
                'u.fname',
                'u.mname',
                'u.lname',
                'u.suffix',
                'd.code as division',
                's.code as section',
                'de.description as designation',
                'ps.salary_grade',
                'ps.step'
            )
            ->get();

        if ($list->isEmpty()) {
            return $list;
        }

        $componentRows = DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', $list->pluck('id'))
            ->select('payroll_employee_id', 'code', 'amount')
            ->get()
            ->groupBy('payroll_employee_id');

        $legacyMap = $this->getLegacyComponentMap();

        foreach ($list as $item) {
            $components = $componentRows->get($item->id, collect());
            $codeTotals = [];
            foreach ($components as $component) {
                $code = strtoupper((string) $component->code);
                $codeTotals[$code] = ($codeTotals[$code] ?? 0) + (float) $component->amount;
            }

            foreach ($legacyMap as $field => $code) {
                $item->$field = $codeTotals[$code] ?? 0;
            }
        }

        return $list;
    }

    private function computeWithholdingTax($annualGross, $purpose)
    {
        $rules = DB::table('hris.payroll_tax_rules')
            ->where('is_active', 1)
            ->where('purpose', $purpose)
            ->orderBy('min_annual')
            ->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        $modes = $rules->pluck('tax_mode')->unique();
        if ($modes->count() > 1) {
            // Data anomaly: active rules for this purpose should never mix modes
            // (savePayrollTaxRules blocks this going forward). Fall back to the
            // mode of the lowest bracket rather than silently folding a flat
            // rule's rate into a progressive marginal sum, or vice versa.
            \Illuminate\Support\Facades\Log::warning("Payroll tax rules for purpose \"{$purpose}\" mix tax modes; using the first bracket's mode and ignoring the rest.", [
                'modes' => $modes->values()->all(),
            ]);
            $dominantMode = $rules->first()->tax_mode;
            $rules = $rules->where('tax_mode', $dominantMode)->values();
        }

        $hasProgressive = $rules->contains(function ($rule) {
            return $rule->tax_mode === 'progressive';
        });

        if ($hasProgressive) {
            $tax = 0;
            foreach ($rules as $rule) {
                $min = (float) $rule->min_annual;
                $max = $rule->max_annual !== null ? (float) $rule->max_annual : $annualGross;
                if ($annualGross <= $min) {
                    continue;
                }
                $upper = min($annualGross, $max);
                $taxableSlice = max(0, $upper - $min);
                $tax += $taxableSlice * ((float) $rule->rate_percent / 100);
            }
            return $tax;
        }

        $matched = $rules
            ->filter(function ($rule) use ($annualGross) {
                $min = (float) $rule->min_annual;
                $max = $rule->max_annual !== null ? (float) $rule->max_annual : null;
                if ($annualGross < $min) {
                    return false;
                }
                if ($max !== null && $annualGross > $max) {
                    return false;
                }
                return true;
            })
            ->sortByDesc('min_annual')
            ->first();

        if (!$matched) {
            return 0;
        }

        return $annualGross * ((float) $matched->rate_percent / 100);
    }

    /**
     * Checks a purpose's full set of would-be-active tax rules for conflicts
     * before they are persisted. Returns a human-readable error, or null if
     * the set is consistent.
     *
     * @param array<int, array{min_annual: mixed, max_annual: mixed, tax_mode: string}> $activeRules
     */
    public function validateTaxRuleSet(string $purpose, array $activeRules): ?string
    {
        if (count($activeRules) < 2) {
            return null;
        }

        $modes = collect($activeRules)->pluck('tax_mode')->unique();
        if ($modes->count() > 1) {
            return "Tax rules for purpose \"{$purpose}\" mix tax modes (" . $modes->implode(', ') . "). All active rules for a purpose must use the same tax mode.";
        }

        foreach ($activeRules as $rule) {
            $min = (float) $rule['min_annual'];
            $max = $rule['max_annual'] !== null ? (float) $rule['max_annual'] : null;
            if ($max !== null && $max <= $min) {
                return "Tax rule for purpose \"{$purpose}\" has max_annual ({$max}) not greater than min_annual ({$min}).";
            }
        }

        $sorted = collect($activeRules)->sortBy(fn($rule) => (float) $rule['min_annual'])->values();
        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $a = $sorted[$i];
            $b = $sorted[$i + 1];
            if ($this->taxRangesOverlap($a, $b)) {
                $aMax = $a['max_annual'] !== null ? $a['max_annual'] : '∞';
                $bMax = $b['max_annual'] !== null ? $b['max_annual'] : '∞';
                return "Tax rules for purpose \"{$purpose}\" have overlapping ranges: [{$a['min_annual']}, {$aMax}) and [{$b['min_annual']}, {$bMax}).";
            }
        }

        return null;
    }

    private function taxRangesOverlap(array $a, array $b): bool
    {
        $aMin = (float) $a['min_annual'];
        $aMax = $a['max_annual'] !== null ? (float) $a['max_annual'] : INF;
        $bMin = (float) $b['min_annual'];
        $bMax = $b['max_annual'] !== null ? (float) $b['max_annual'] : INF;

        return $aMin < $bMax && $bMin < $aMax;
    }

    /**
     * Checks a resulting set of components (existing + incoming, keyed by
     * code) for formula problems before anything is persisted: a percentage
     * component must name a valid, non-self base and a non-negative rate,
     * and its base chain must not loop back to itself. Returns a
     * human-readable error, or null if the set is consistent.
     *
     * @param array<string, array{calculation_type: string, base_type: ?string, base_component_code: ?string, rate_percent: mixed}> $componentsByCode
     */
    public function validateComponentFormulas(array $componentsByCode): ?string
    {
        foreach ($componentsByCode as $code => $component) {
            if (($component['calculation_type'] ?? 'fixed') !== 'percentage') {
                continue;
            }

            $baseType = $component['base_type'] ?? null;
            $baseCode = $component['base_component_code'] ?? null;
            $rate = $component['rate_percent'] ?? null;

            if ($baseType === 'component') {
                if (empty($baseCode)) {
                    return "Component \"{$code}\" is percentage-based but has no base component selected.";
                }
                if ($baseCode === $code) {
                    return "Component \"{$code}\" cannot reference itself as its base.";
                }
                if (!array_key_exists($baseCode, $componentsByCode)) {
                    return "Component \"{$code}\" references unknown base component \"{$baseCode}\".";
                }

                $visited = [$code => true];
                $current = $baseCode;
                while ($current !== null) {
                    if (isset($visited[$current])) {
                        return "Circular reference detected: component \"{$code}\" eventually references itself through \"{$current}\".";
                    }
                    $visited[$current] = true;
                    $currentComponent = $componentsByCode[$current] ?? null;
                    if (!$currentComponent
                        || ($currentComponent['calculation_type'] ?? 'fixed') !== 'percentage'
                        || ($currentComponent['base_type'] ?? null) !== 'component') {
                        break;
                    }
                    $current = $currentComponent['base_component_code'] ?? null;
                }
            } elseif ($baseType !== 'basic_salary') {
                return "Component \"{$code}\" is percentage-based but has no valid base type.";
            }

            if ($rate === null || (float) $rate < 0) {
                return "Component \"{$code}\" has an invalid percentage rate.";
            }
        }

        return null;
    }

    /**
     * Checks a set of per-employee component overrides for a negative
     * rate_percent_override before anything is persisted. A missing or null
     * rate_percent_override is fine (it just means "use the catalog rate").
     * Returns a human-readable error, or null if every override is valid.
     *
     * @param array<int, array{code?: string, rate_percent_override?: mixed}> $components
     */
    public function validateRatePercentOverrides(array $components): ?string
    {
        foreach ($components as $component) {
            if (!array_key_exists('rate_percent_override', $component)) {
                continue;
            }
            $rateOverride = $component['rate_percent_override'];
            if ($rateOverride === null || $rateOverride === '') {
                continue;
            }
            if (!is_numeric($rateOverride)) {
                $code = $component['code'] ?? '?';
                return "Component \"{$code}\" has an invalid rate override.";
            }
            if ((float) $rateOverride < 0 || (float) $rateOverride > 99999.999) {
                $code = $component['code'] ?? '?';
                return "Component \"{$code}\" has an invalid rate override.";
            }
        }

        return null;
    }

    private function generateAcronym($designation)
    {
        if (!$designation) return '';

        $words = explode(' ', $designation);
        $rank = array_pop($words); // last word is rank

        // Convert Roman numeral I–VII to number
        $romanToNumber = [
            'I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4',
            'V' => '5', 'VI' => '6', 'VII' => '7'
        ];
        $rankNumber = $romanToNumber[strtoupper($rank)] ?? $rank;

        // Get first letters of remaining words
        $acronym = '';
        foreach ($words as $word) {
            if (preg_match('/[A-Za-z]/', $word[0])) {
                $acronym .= strtoupper($word[0]);
            }
        }

        return $acronym . $rankNumber;
    }


    public function getWorkingDays($year, $month)
    {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $count = 0;

        // Get holidays for the month/year
        $holidays = DB::table('hris.calendar_holidays')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->pluck('date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();

        while ($startDate <= $endDate) {
            if ($startDate->isWeekday() && !in_array($startDate->toDateString(), $holidays)) {
                $count++;
            }
            $startDate->addDay();
        }

        return $count; // only integer
    }

    public function getPayrollPeriod($id)
    {
        return DB::table('hris.payrolls')
            ->where('id', '=', $id)
            ->first();
    }

    public function getWorkingDaysByRange($startDate, $endDate)
    {
        if (!$startDate || !$endDate) {
            return 0;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $holidays = DB::table('hris.calendar_holidays')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();

        $count = 0;
        while ($start <= $end) {
            if ($start->isWeekday() && !in_array($start->toDateString(), $holidays)) {
                $count++;
            }
            $start->addDay();
        }

        return $count;
    }

    public function getTotalDaysByRange($startDate, $endDate)
    {
        if (!$startDate || !$endDate) {
            return 0;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        return $start->diffInDays($end) + 1;
    }

    public function savePayrollEmployee($payroll_id, $employee, $totalLateUndertimeMinutes, $totalAbsentDays, $totalWorkingDays)
    {
        $period = $this->getPayrollPeriod($payroll_id);
        $periodStart = $period->cutoff_start_date ?? $period->start_date ?? null;
        $periodEnd = $period->cutoff_end_date ?? $period->end_date ?? null;

        $salary = DB::table('hris.payroll_settings')
            ->where('userid', '=', $employee["userid"])
            ->first();

        // ---- Basic Salary & Rates ----
        $basic_salary = $salary->basic_salary ?? 0.00;
        $totalWorkingDays = max((int) $totalWorkingDays, 1);
        $daily_rate = $basic_salary / $totalWorkingDays;
        $hourly_rate = $daily_rate / 8;
        $minute_rate = $hourly_rate / 60;

        $lateUndertimeDeduction = $totalLateUndertimeMinutes * $minute_rate;
        $absentDeduction = $totalAbsentDays * $daily_rate;
        $totalTardinessDeductions = $lateUndertimeDeduction + $absentDeduction;

        $dynamicComponents = $this->getEmployeeDynamicComponents($employee["userid"], $periodStart, $periodEnd, (float) $basic_salary);
        $computedComponents = $this->getComputedComponents($employee, $totalTardinessDeductions);
        $allComponents = $dynamicComponents->merge($computedComponents);
        $dynamicEarnings = $allComponents->where('type', 'earning')->sum('amount');
        $dynamicDeductions = $allComponents->where('type', 'deduction')->sum('amount');

        // ---- Gross Pay (before tardiness deduction) ----
        $gross_pay = ($basic_salary + $dynamicEarnings);
        $taxableEarnings = $allComponents
            ->where('type', 'earning')
            ->where('taxable', true)
            ->sum('amount');
        $taxableGrossPay = ($basic_salary + $taxableEarnings) - $totalTardinessDeductions;

        // ---- Withholding Tax ----
        $taxPurpose = $period->tax_purpose ?? 'Payroll Monthly Salary';
        $annualGross = $taxableGrossPay * 12;
        $withholding_tax = $this->computeWithholdingTax($annualGross, $taxPurpose) / 12;

        // ---- Final Deductions ----
        $withholdingComponent = (object) [
            'payroll_component_id' => null,
            'code' => 'WITHHOLDING_TAX',
            'name' => 'Withholding Tax',
            'type' => 'deduction',
            'taxable' => false,
            'amount' => $withholding_tax,
        ];
        $allComponents = $allComponents->push($withholdingComponent);
        $dynamicDeductions = $allComponents->where('type', 'deduction')->sum('amount');
        $total_deductions = $dynamicDeductions;

        // ---- Net Pay ----
        $net_pay = $taxableGrossPay - $total_deductions;
        $first_half_10th = $net_pay / 2;
        $second_half_25th = $net_pay / 2;
        // ---- Insert ----
        $payrollEmployeeId = DB::table('hris.payroll_employees')->insertGetId([
            'payroll_id' => $payroll_id,
            'employee_id' => $employee['userid'],
            'division' => 1,
            'section' => 2,
            'basic_salary' => $basic_salary,
            'other_earnings' => $dynamicEarnings,
            'late_absent' => $totalTardinessDeductions,
            'gross_pay' => $taxableGrossPay,
            'other_deductions' => $dynamicDeductions,

            // Split pay
            'first_half_10th' => $first_half_10th,
            'second_half_25th' => $second_half_25th,

            // Final
            'deductions' => $total_deductions,
            'net_pay' => $net_pay,

            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->storePayrollEmployeeComponents($payrollEmployeeId, $allComponents);

        return $payrollEmployeeId;
    }

    public function deletePayrollEmployees($payroll_id)
    {
        return DB::table('hris.payroll_employees')
            ->where('payroll_id', '=', $payroll_id)
            ->delete();
    }

    public function getPayrollEmployeeCount($payroll_id)
    {
        return DB::table('hris.payroll_employees')
            ->where('payroll_id', '=', $payroll_id)
            ->count();
    }

    private function getEmployeeDynamicComponents($userId, $periodStart, $periodEnd, float $basicSalary)
    {
        $globalRows = DB::table('hris.payroll_components as pc')
            ->leftJoin('hris.employee_payroll_components as epc', function ($join) use ($userId) {
                $join->on('epc.payroll_component_id', '=', 'pc.id')
                    ->where('epc.userid', '=', $userId);
            })
            ->where('pc.is_active', 1)
            ->where('pc.is_global', 1)
            ->select(
                'pc.id as payroll_component_id',
                'pc.code',
                'pc.name',
                'pc.type',
                'pc.taxable',
                'pc.default_amount',
                'pc.calculation_type',
                'pc.base_type',
                'pc.base_component_code',
                'pc.rate_percent',
                'epc.amount_override',
                'epc.rate_percent_override',
                'epc.is_active as employee_active',
                'epc.is_excluded',
                'epc.effective_from',
                'epc.effective_to'
            )
            ->get()
            ->filter(function ($row) use ($periodStart, $periodEnd) {
                if ($row->is_excluded || $row->employee_active === 0) {
                    return false;
                }

                if ($periodStart && $row->effective_to) {
                    if ($row->effective_to < $periodStart) return false;
                }
                if ($periodEnd && $row->effective_from) {
                    if ($row->effective_from > $periodEnd) return false;
                }
                return true;
            });

        $globals = $this->resolveComponentAmounts($globalRows, $basicSalary)
            ->map(function ($row) {
                return (object) [
                    'payroll_component_id' => $row->payroll_component_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'taxable' => $row->taxable,
                    'amount' => $row->amount,
                ];
            });

        $employeeSpecific = DB::table('hris.employee_payroll_components as epc')
            ->join('hris.payroll_components as pc', 'pc.id', '=', 'epc.payroll_component_id')
            ->where('epc.userid', $userId)
            ->where('pc.is_active', 1)
            ->where(function ($q) {
                $q->where('pc.is_global', 0)->orWhereNull('pc.is_global');
            })
            ->where('epc.is_active', 1)
            ->select(
                'pc.id as payroll_component_id',
                'pc.code',
                'pc.name',
                'pc.type',
                'pc.taxable',
                'epc.amount_override',
                'epc.amount'
            )
            ->get()
            ->map(function ($row) {
                $amount = $row->amount_override !== null ? $row->amount_override : $row->amount;
                return (object) [
                    'payroll_component_id' => $row->payroll_component_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'taxable' => $row->taxable,
                    'amount' => $amount ?? 0,
                ];
            });

        return $globals->merge($employeeSpecific);
    }

    /**
     * Resolves each row's final 'amount' in dependency order (a component's
     * base is resolved before the component that references it), per
     * docs/superpowers/specs/2026-07-10-computed-payroll-components-design.md.
     * Returns the same rows with 'amount' set; every other field untouched.
     */
    private function resolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
    {
        $byCode = $rows->keyBy('code');
        $order = $this->topoSortComponentCodes($byCode);

        $resolved = [];
        foreach ($order as $code) {
            $resolved[$code] = $this->resolveComponentAmount($byCode[$code], $resolved, $basicSalary);
        }

        return $rows->map(function ($row) use ($resolved) {
            $row->amount = $resolved[$row->code] ?? 0.0;
            return $row;
        });
    }

    /**
     * A component in a reference cycle is deliberately left out of
     * $resolved by topoSortComponentCodes() (it logs a warning and bails
     * before recording that code) - the `?? 0.0` fallback below means every
     * component in the cycle silently resolves to 0 rather than looping
     * forever. Save-time validation (validateComponentFormulas()) is what
     * actually keeps cycles out of the database; this is just a defensive
     * backstop.
     */
    private function resolveComponentAmount($row, array $resolved, float $basicSalary): float
    {
        if ($row->amount_override !== null) {
            return round((float) $row->amount_override, 3);
        }

        $calculationType = $row->calculation_type ?? 'fixed';
        if ($calculationType !== 'percentage') {
            return round((float) ($row->default_amount ?? 0), 3);
        }

        $rateOverride = $row->rate_percent_override ?? null;
        $rate = $rateOverride !== null ? (float) $rateOverride : (float) ($row->rate_percent ?? 0);

        if (($row->base_type ?? null) === 'basic_salary') {
            return round($basicSalary * $rate / 100, 3);
        }

        if (($row->base_type ?? null) === 'component') {
            $baseAmount = $resolved[$row->base_component_code] ?? 0.0;
            return round($baseAmount * $rate / 100, 3);
        }

        return 0.0;
    }

    /**
     * Post-order DFS: a component's base (if any) is appended to $order
     * before the component itself, so resolveComponentAmount() can always
     * look up an already-resolved base. A code revisited while still
     * 'visiting' is a cycle - logged and left out of $order (and therefore
     * out of $resolved in resolveComponentAmounts()).
     */
    private function topoSortComponentCodes(\Illuminate\Support\Collection $byCode): array
    {
        $order = [];
        $state = [];

        $visit = function ($code) use (&$visit, &$order, &$state, $byCode) {
            if (($state[$code] ?? null) === 'done') {
                return;
            }
            if (($state[$code] ?? null) === 'visiting') {
                \Illuminate\Support\Facades\Log::warning("Payroll component formula cycle detected involving \"{$code}\"; treating as 0.");
                return;
            }

            $state[$code] = 'visiting';
            $row = $byCode->get($code);
            if ($row && ($row->calculation_type ?? 'fixed') === 'percentage' && ($row->base_type ?? null) === 'component') {
                $baseCode = $row->base_component_code;
                if ($baseCode !== null && $byCode->has($baseCode)) {
                    $visit($baseCode);
                }
            }
            $state[$code] = 'done';
            $order[] = $code;
        };

        foreach ($byCode->keys() as $code) {
            $visit($code);
        }

        return $order;
    }

    /**
     * Components eligible for a quick per-cutoff adjustment field in the
     * Review & Compute modal. Adding/removing one is purely a Settings >
     * Payroll Components edit (toggling is_cutoff_adjustable) - no code change.
     */
    public function getCutoffAdjustableComponents()
    {
        return DB::table('hris.payroll_components')
            ->where('is_active', 1)
            ->where('is_cutoff_adjustable', 1)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'type', 'sort_order']);
    }

    private function getComputedComponents($employee, $totalTardinessDeductions)
    {
        $components = [];

        // Basic Pay adjustment isn't a payroll_components row - it adjusts the
        // basic salary itself, which lives on payroll_settings - so it stays
        // a fixed, non-dynamic entry rather than going through the loop below.
        $salaryAdjustment = (float) ($employee['salary_adjustment'] ?? 0.00);
        if ($salaryAdjustment !== 0.0) {
            $components[] = (object) [
                'payroll_component_id' => null,
                'code' => 'SALARY_ADJUSTMENT',
                'name' => 'SALARY_ADJUSTMENT',
                'type' => 'earning',
                'taxable' => false,
                'amount' => $salaryAdjustment,
            ];
        }

        // Every other quick per-cutoff adjustment is driven by whichever
        // payroll_components rows are flagged is_cutoff_adjustable - adding a
        // new adjustable component in Settings > Payroll Components is all
        // that's needed to make it available here, no code change required.
        // One signed amount per component: positive adds to it, negative
        // reduces/refunds it (this replaces the old separate "adjustment" vs
        // "refund" codes with a single signed field per component).
        $adjustableComponents = $this->getCutoffAdjustableComponents();

        $adjustments = $employee['adjustments'] ?? [];

        foreach ($adjustableComponents as $component) {
            $amount = (float) ($adjustments[$component->code] ?? 0.00);
            if ($amount === 0.0) {
                continue;
            }

            $components[] = (object) [
                'payroll_component_id' => null,
                'code' => $component->code . '_ADJUSTMENT',
                'name' => $component->name . ' (Cutoff Adjustment)',
                'type' => $component->type,
                'taxable' => false,
                'amount' => $amount,
            ];
        }

        return collect($components);
    }

    private function getLegacyComponentMap()
    {
        return [
            'pera' => 'PERA',
            'rata' => 'RATA',
            'subsistence' => 'SUBSISTENCE',
            'laundry' => 'LAUNDRY',
            'hazard' => 'HAZARD',
            'longevity' => 'LONGEVITY',
            'cellphone_allowance' => 'CELLPHONE_ALLOWANCE',
            'gsis_ps' => 'GSIS_PS',
            'gsis_gs' => 'GSIS_GS',
            'gsis_ec' => 'GSIS_EC',
            'hdmf' => 'HDMF',
            'phic' => 'PHIC',
            'pagibig_cal' => 'PAGIBIG_CAL',
            'pagibig_mpl' => 'PAGIBIG_MPL',
            'pagibig_mp2' => 'PAGIBIG_MP2',
            'pagibig_mp2_2nd' => 'PAGIBIG_MP2_2ND',
            'pagibig_mp2_3rd' => 'PAGIBIG_MP2_3RD',
            'gsis_consoloan' => 'GSIS_CONSOLOAN',
            'gsis_emergency_loan' => 'GSIS_EMERGENCY_LOAN',
            'gsis_educational_loan' => 'GSIS_EDUCATIONAL_LOAN',
            'gsis_policy_loan' => 'GSIS_POLICY_LOAN',
            'gsis_opt_life_loan' => 'GSIS_OPT_LIFE_LOAN',
            'gsis_gfal' => 'GSIS_GFAL',
            'gsis_mpl' => 'GSIS_MPL',
            'gsis_cpl' => 'GSIS_CPL',
            'gsis_mpl_lite' => 'GSIS_MPL_LITE',
            'cfi_coop' => 'CFI_COOP',
            'first_valley_bank' => 'FIRST_VALLEY_BANK',
            'tdhea' => 'TDHEA',
            'dbp_loan' => 'DBP_LOAN',
            'ucpb_loan' => 'UCPB_LOAN',
            'meds_payable' => 'MEDS_PAYABLE',
            'sss_deduction' => 'SSS_DEDUCTION',
            'withholding_tax' => 'WITHHOLDING_TAX',
            'withholding_tax_adjustment' => 'WITHHOLDING_TAX_ADJUSTMENT',
            'phic_contri_adj' => 'PHIC_CONTRI_ADJ',
            'phic_refund_from_eo' => 'PHIC_REFUND_FROM_EO',
            'sss_adjustment' => 'SSS_ADJUSTMENT',
            'gsis_ps_lwop_2pct' => 'GSIS_PS_LWOP_2PCT',
            'gsis_gs_lwop_2pct' => 'GSIS_GS_LWOP_2PCT',
            'gsis_plreg_refund' => 'GSIS_PLREG_REFUND',
            'gsis_mpl_refund' => 'GSIS_MPL_REFUND',
            'gsis_emergency_refund' => 'GSIS_EMERGENCY_REFUND',
            'gsis_consoloan_refund' => 'GSIS_CONSOLOAN_REFUND',
            'subsistence_deduction_from_leave' => 'SUBSISTENCE_DEDUCTION_FROM_LEAVE',
            'second_half_adjustment_resigned_employee' => 'SECOND_HALF_ADJUSTMENT_RESIGNED',
            'salary_adjustment' => 'SALARY_ADJUSTMENT',
            'hazard_adjustment' => 'HAZARD_ADJUSTMENT',
            'pera_adjustment' => 'PERA_ADJUSTMENT',
            'subsistence_adjustment' => 'SUBSISTENCE_ADJUSTMENT',
            'laundry_adjustment' => 'LAUNDRY_ADJUSTMENT',
        ];
    }

    public function getOrCreateCutoffSummary($employeeId, $startDate, $endDate, $generatedBy = null)
    {
        $table = $this->resolveCutoffSummaryTable();
        $master = $this->getOrCreateCutoffMaster($startDate, $endDate, null, $generatedBy);

        $summary = DB::table($table)
            ->where('employee_id', $employeeId)
            ->where('cutoff_id', $master->id)
            ->first();

        if ($summary) {
            return $summary;
        }

        $dtrService = app(DTRServices::class);
        $result = $dtrService->getCutoffBreakdown($employeeId, $startDate, $endDate);
        $totalWorkingDays = $this->getWorkingDaysByRange($startDate, $endDate);
        $totalPresent = $dtrService->getTotalPresentByRange($employeeId, $startDate, $endDate);
        $lateMinutes = $result['late_minutes'] ?? 0;
        $undertimeMinutes = $result['undertime_minutes'] ?? 0;
        $absences = $result['absences'] ?? 0;
        $daysPresent = max($totalPresent, 0);

        DB::table($table)->insert($this->withCreditWithoutPayDays($table, [
            'employee_id' => $employeeId,
            'cutoff_id' => $master->id,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'total_working_days' => $totalWorkingDays,
            'scheduled_work_days' => $dtrService->getScheduledWorkDaysByRange($employeeId, $startDate, $endDate),
            'days_present' => $daysPresent,
            'days_absent' => $absences,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'approved_overtime_minutes' => (int) ($result['approved_overtime_minutes'] ?? 0),
            'approved_pass_slips_minutes' => (int) ($result['approved_pass_slips_minutes'] ?? 0),
            'approved_leave_days' => (int) ($result['approved_leave_days'] ?? 0),
            'lwop_days' => round((float) ($result['lwop_days'] ?? 0), 3),
            'hospital_order_days' => (int) ($result['hospital_order_days'] ?? 0),
            'generated_by' => $generatedBy,
            'generated_at' => now(),
            'remarks' => $master->title ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $employeeId, $startDate, $endDate));

        $this->persistCutoffSummaryDays($master->id, $employeeId, $startDate, $endDate);

        return DB::table($table)
            ->where('employee_id', $employeeId)
            ->where('cutoff_id', $master->id)
            ->first();
    }

    private function persistCutoffSummaryDays($cutoffId, $employeeId, $startDate, $endDate): void
    {
        $daily = app(DTRServices::class)->getDailyBreakdown($employeeId, $startDate, $endDate);

        DB::table('hris.cutoff_summary_days')
            ->where('cutoff_id', $cutoffId)
            ->where('employee_id', $employeeId)
            ->delete();

        $rows = $daily->map(function ($day, $date) use ($cutoffId, $employeeId) {
            return [
                'cutoff_id' => $cutoffId,
                'employee_id' => $employeeId,
                'date' => $date,
                'late_minutes' => $day['late_minutes'],
                'undertime_minutes' => $day['undertime_minutes'],
                'is_absent' => $day['is_absent'] ? 1 : 0,
                'is_holiday' => $day['is_holiday'] ? 1 : 0,
                'exemption_reason' => $day['exemption_reason'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->values()->all();

        if (!empty($rows)) {
            DB::table('hris.cutoff_summary_days')->insert($rows);
        }
    }

    public function generateCutoffSummary($employeeId, $startDate, $endDate, $title = null, $generatedBy = null)
    {
        $table = $this->resolveCutoffSummaryTable();
        $master = $this->getOrCreateCutoffMaster($startDate, $endDate, $title, $generatedBy);
        $dtrService = app(DTRServices::class);
        $result = $dtrService->getCutoffBreakdown($employeeId, $startDate, $endDate);
        $totalWorkingDays = $this->getWorkingDaysByRange($startDate, $endDate);
        $totalPresent = $dtrService->getTotalPresentByRange($employeeId, $startDate, $endDate);
        $lateMinutes = $result['late_minutes'] ?? 0;
        $undertimeMinutes = $result['undertime_minutes'] ?? 0;
        $absences = $result['absences'] ?? 0;
        $daysPresent = max($totalPresent, 0);

        $payload = [
            'employee_id' => $employeeId,
            'cutoff_id' => $master->id,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'total_working_days' => $totalWorkingDays,
            'scheduled_work_days' => $dtrService->getScheduledWorkDaysByRange($employeeId, $startDate, $endDate),
            'days_present' => $daysPresent,
            'days_absent' => $absences,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'approved_overtime_minutes' => (int) ($result['approved_overtime_minutes'] ?? 0),
            'approved_pass_slips_minutes' => (int) ($result['approved_pass_slips_minutes'] ?? 0),
            'approved_leave_days' => (int) ($result['approved_leave_days'] ?? 0),
            'lwop_days' => round((float) ($result['lwop_days'] ?? 0), 3),
            'hospital_order_days' => (int) ($result['hospital_order_days'] ?? 0),
            'generated_by' => $generatedBy,
            'generated_at' => now(),
            'remarks' => $master->title ?? $title,
            'updated_at' => now(),
        ];
        $payload = $this->withCreditWithoutPayDays($table, $payload, $employeeId, $startDate, $endDate);

        DB::table($table)->updateOrInsert(
            [
                'employee_id' => $employeeId,
                'cutoff_id' => $master->id,
            ],
            $payload + ['created_at' => now()]
        );

        $this->persistCutoffSummaryDays($master->id, $employeeId, $startDate, $endDate);

        return DB::table($table)
            ->where('employee_id', $employeeId)
            ->where('cutoff_id', $master->id)
            ->first();
    }

    public function getCutoffSummaryTableName()
    {
        return $this->resolveCutoffSummaryTable();
    }

    public function getCutoffSummaryMasterTableName()
    {
        return $this->resolveCutoffSummaryMasterTable();
    }

    public function getCutoffMasterByPeriod($startDate, $endDate, $title = null)
    {
        $table = $this->resolveCutoffSummaryMasterTable();
        $query = DB::table($table)
            ->where('period_start', $startDate)
            ->where('period_end', $endDate);

        if ($title !== null) {
            $query->where('title', $title);
        }

        return $query->orderByDesc('id')->first();
    }

    public function getOrCreateCutoffMaster($startDate, $endDate, $title = null, $generatedBy = null)
    {
        $table = $this->resolveCutoffSummaryMasterTable();
        $existing = $this->getCutoffMasterByPeriod($startDate, $endDate, $title);
        if ($existing) {
            return $existing;
        }

        $id = DB::table($table)->insertGetId([
            'title' => $title,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'generated_by' => $generatedBy,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table($table)->where('id', $id)->first();
    }

    private function resolveCutoffSummaryTable()
    {
        if ($this->tableExistsInSchema('hris', 'cutoff_summaries')) {
            return 'hris.cutoff_summaries';
        }

        return 'cutoff_summaries';
    }

    private function resolveCutoffSummaryMasterTable()
    {
        if ($this->tableExistsInSchema('hris', 'cutoff_summaries_master')) {
            return 'hris.cutoff_summaries_master';
        }

        return 'cutoff_summaries_master';
    }

    /**
     * Adds credit_without_pay_days (days without pay for earned credits - see
     * DTRServices::getCreditWithoutPayDaysByRange) to a cutoff summary row.
     * Skipped until migration 2026_11_10_100000 has run, so generating
     * cutoffs keeps working before it is deployed.
     */
    private function withCreditWithoutPayDays(string $table, array $payload, $employeeId, $startDate, $endDate): array
    {
        if ($this->cutoffSummaryHasColumn($table, 'credit_without_pay_days')) {
            $payload['credit_without_pay_days'] = app(DTRServices::class)
                ->getCreditWithoutPayDaysByRange($employeeId, $startDate, $endDate);
        }

        return $payload;
    }

    private function cutoffSummaryHasColumn(string $table, string $column): bool
    {
        static $hasColumn = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $hasColumn)) {
            [$schema, $name] = strpos($table, '.') !== false ? explode('.', $table, 2) : [DB::getDatabaseName(), $table];
            $hasColumn[$key] = (bool) DB::selectOne(
                'SELECT 1 AS found FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [$schema, $name, $column]
            );
        }

        return $hasColumn[$key];
    }

    private function tableExistsInSchema(string $schema, string $table): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$schema, $table]
        );

        return $result && (int) $result->count > 0;
    }

    private function storePayrollEmployeeComponents($payrollEmployeeId, $components)
    {
        if ($components->isEmpty()) {
            return;
        }

        $rows = $components->map(function ($component) use ($payrollEmployeeId) {
            return [
                'payroll_employee_id' => $payrollEmployeeId,
                'payroll_component_id' => $component->payroll_component_id,
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
                'taxable' => $component->taxable ? 1 : 0,
                'amount' => $component->amount ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        DB::table('hris.payroll_employee_components')->insert($rows);
    }


}
