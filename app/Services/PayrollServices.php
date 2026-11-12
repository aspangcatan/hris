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

    /**
     * The earning and deduction columns of a payroll, taken from the lines saved
     * with it (payroll_employee_components) and named as saved - not from today's
     * Earnings & Deductions settings. So a payroll always shows exactly what it was
     * computed with: a component deactivated later still shows, one added later does
     * not, and adjustments and prior-period lines get their own columns.
     * Withholding tax has its own fixed column, so it is left out here.
     *
     * @return array{earning: \Illuminate\Support\Collection, deduction: \Illuminate\Support\Collection, amounts: \Illuminate\Support\Collection}
     */
    public function payrollComponentColumns($payrollEmployees): array
    {
        $ids = collect($payrollEmployees)->pluck('id');
        $lines = DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', $ids)
            ->get(['payroll_employee_id', 'code', 'name', 'type', 'amount']);

        $sortOrder = DB::table('hris.payroll_components')->pluck('sort_order', 'code');
        $position = function (string $code) use ($sortOrder): array {
            if ($code === 'SALARY_ADJUSTMENT') {
                return [0, 0, $code, 0];                                // next to basic pay
            }
            if (strpos($code, 'PRIOR_PERIOD_') === 0) {
                return [3, 0, $code, 0];                                // last
            }
            $base = preg_replace('/_ADJUSTMENT$/', '', $code);
            if ($sortOrder->has($base)) {
                return [1, (int) $sortOrder[$base], $base, $code === $base ? 0 : 1]; // an adjustment follows its component
            }

            return [2, 0, $code, 0];
        };

        $columns = $lines->where('code', '<>', 'WITHHOLDING_TAX')
            ->groupBy('code')
            ->map(fn($group, $code) => (object) [
                'code' => $code,
                // Lines saved before the label was added carry the raw code as their name.
                'name' => $code === 'SALARY_ADJUSTMENT' ? 'Basic Pay Adjustment' : ($group->first()->name ?: $code),
                'type' => $group->first()->type,
            ])
            ->sortBy(fn($column) => $position($column->code))
            ->values();

        return [
            'earning' => $columns->where('type', 'earning')->values(),
            'deduction' => $columns->where('type', 'deduction')->values(),
            'amounts' => $lines->groupBy('payroll_employee_id')
                ->map(fn($rows) => $rows->groupBy('code')->map(fn($same) => round($same->sum('amount'), 2))),
        ];
    }

    public function exportRegularPayroll($payrollEmployees, $exportPath, $payroll = null)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Summary');

        $breakdown = $this->payrollComponentColumns($payrollEmployees);
        $amount = fn($item, $code) => (float) (($breakdown['amounts']->get($item->id) ?? collect())[$code] ?? 0);

        // [header, value, kind]: kind is text, plain (centred number) or money (summed in the totals row).
        $columns = [
            ['No.', null, 'plain'],
            ['NAME', fn($item) => $this->formatEmployeeName($item), 'text'],
            ['FAMILY NAME', fn($item) => $item->lname ?? '', 'text'],
            ['FIRST NAME', fn($item) => $item->fname ?? '', 'text'],
            ['MIDDLE NAME', fn($item) => $item->mname ?? '', 'text'],
            ['SUFFIX', fn($item) => $item->suffix ?? '', 'text'],
            ['MI', fn($item) => !empty($item->mname) ? strtoupper(substr($item->mname, 0, 1)) . '.' : '', 'text'],
            ['DIVISION', fn($item) => $item->division ?? '', 'text'],
            ['DESIGNATION', fn($item) => $item->designation ?? '', 'text'],
            ['zz', fn($item) => $this->generateAcronym($item->designation ?? ''), 'text'],
            ['SG', fn($item) => $item->salary_grade ?? '', 'plain'],
            ['Step', fn($item) => $item->step ?? '', 'plain'],
            ['BASIC PAY', fn($item) => (float) $item->basic_salary, 'money'],
            ['LATE / ABSENT / LWOP', fn($item) => (float) $item->late_absent, 'money'],
        ];
        foreach ($breakdown['earning'] as $component) {
            $columns[] = [strtoupper($component->name), fn($item) => $amount($item, $component->code), 'money'];
        }
        $columns[] = ['GROSS PAY', fn($item) => (float) $item->gross_pay, 'money'];
        $columns[] = ['WITHHOLDING TAX', fn($item) => $amount($item, 'WITHHOLDING_TAX'), 'money'];
        foreach ($breakdown['deduction'] as $component) {
            $columns[] = [strtoupper($component->name), fn($item) => $amount($item, $component->code), 'money'];
        }
        $columns[] = ['TOTAL DEDUCTION', fn($item) => (float) $item->deductions, 'money'];
        $columns[] = ['NET PAY', fn($item) => (float) $item->net_pay, 'money'];
        $columns[] = ['1ST HALF 10TH', fn($item) => (float) $item->first_half_10th, 'money'];
        $columns[] = ['2ND HALF 25TH', fn($item) => (float) $item->second_half_25th, 'money'];

        $letter = fn(int $index) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        $lastColLetter = $letter(count($columns) - 1);

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

        foreach ($columns as $index => [$header]) {
            $sheet->setCellValue($letter($index) . $headerRow, $header);
        }

        $row = $dataStartRow;
        foreach (collect($payrollEmployees)->values() as $number => $item) {
            foreach ($columns as $index => [, $value]) {
                $sheet->setCellValue($letter($index) . $row, $value === null ? $number + 1 : $value($item));
            }
            $row++;
        }

        $lastDataRow = $row - 1;
        $totalsRow = $row;
        $sheet->setCellValue("A{$totalsRow}", 'TOTAL (' . count($payrollEmployees) . ' employees)');
        $firstMoney = collect($columns)->search(fn($column) => $column[2] === 'money');
        $sheet->mergeCells("A{$totalsRow}:" . $letter(max(0, $firstMoney - 1)) . $totalsRow);
        foreach ($columns as $index => [, , $kind]) {
            if ($kind === 'money' && $lastDataRow >= $dataStartRow) {
                $col = $letter($index);
                $sheet->setCellValue("{$col}{$totalsRow}", "=SUM({$col}{$dataStartRow}:{$col}{$lastDataRow})");
            }
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
        foreach ($columns as $index => [, , $kind]) {
            $col = $letter($index);
            $range = "{$col}{$dataStartRow}:{$col}{$totalsRow}";
            if ($kind === 'text') {
                $sheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            } elseif ($kind === 'plain') {
                $sheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            } else {
                $sheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->freezePane("A{$dataStartRow}");

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
        // Position, office and grade as they were in the cutoff, when it has a snapshot.
        $list = DB::table('hris.payroll_employees as e')
            ->join('tdh_user.users as u', 'u.id', '=', 'e.employee_id')
            ->join('hris.payrolls as p', 'p.id', '=', 'e.payroll_id')
            ->leftJoin('hris.cutoff_employee_snapshots as ces', function ($join) {
                $join->on('ces.cutoff_id', '=', 'p.cutoff_id')->on('ces.employee_id', '=', 'e.employee_id');
            })
            ->leftJoin('tdh_user.designation as de', 'u.designation', '=', 'de.id')
            ->leftJoin('tdh_user.division as d', 'd.id', '=', DB::raw('COALESCE(ces.division_id, u.division)'))
            ->leftJoin('tdh_user.section as s', 's.id', '=', DB::raw('COALESCE(ces.section_id, u.section)'))
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
                DB::raw('COALESCE(ces.position, de.description) AS designation'),
                DB::raw('COALESCE(ces.salary_grade, ps.salary_grade) AS salary_grade'),
                DB::raw('COALESCE(ces.salary_step, ps.step) AS step')
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

    /** @param \Illuminate\Support\Collection|null $snapshotRules tax rules saved with the cutoff; null reads the live table */
    private function computeWithholdingTax($annualGross, $purpose, $snapshotRules = null)
    {
        $rules = $snapshotRules !== null
            ? $snapshotRules->where('purpose', $purpose)->sortBy(fn($rule) => (float) $rule->min_annual)->values()
            : DB::table('hris.payroll_tax_rules')
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

    /**
     * Working days in a month for the daily rate (CSC: daily rate = monthly
     * salary / 22). Fixed so a half-month or partial cutoff doesn't change the
     * value of one day (audit H1).
     */
    public const WORKING_DAYS_PER_MONTH = 22;

    /** Used only if no default computation exists in Pay Computations. */
    public const STANDARD_HOURS_PER_DAY = 8;

    /** The default computation from Pay Computations settings. */
    public function defaultComputation(): object
    {
        return DB::table('hris.pay_computations')->where('is_default', 1)->first()
            ?? (object) [
                'id' => null,
                'name' => null,
                'working_days_per_month' => self::WORKING_DAYS_PER_MONTH,
                'standard_hours_per_day' => self::STANDARD_HOURS_PER_DAY,
            ];
    }

    /**
     * Daily Base = basic / working days; Hourly = Daily Base / standard hours;
     * Minute = Hourly / 60. Within a computation these are the same for every
     * schedule; only the scheduled daily rate (Hourly x that day's hours) differs.
     * Leaving the divisors out uses the default computation.
     *
     * @return array{daily: float, hourly: float, minute: float}
     */
    public function payRates(float $basicSalary, ?float $workingDays = null, ?float $standardHours = null): array
    {
        if (!$workingDays || !$standardHours) {
            $default = $this->defaultComputation();
            $workingDays = $workingDays ?: (float) $default->working_days_per_month;
            $standardHours = $standardHours ?: (float) $default->standard_hours_per_day;
        }
        $daily = $basicSalary / $workingDays;
        $hourly = $daily / $standardHours;

        return ['daily' => $daily, 'hourly' => $hourly, 'minute' => $hourly / 60];
    }

    /**
     * What to charge for absences, LWOP and late/undertime/pass-slip minutes. Each
     * day counts at its own scheduled length and its own shift's rate. The saved
     * day rows are used when they agree with the totals; otherwise (older cutoffs)
     * a day is a standard day at $minuteRate.
     *
     * @return array{absent_minutes: int, lwop_minutes: int, absent_amount: float, lwop_amount: float, other_amount: float}
     */
    public function scheduledCharges($cutoffId, int $employeeId, float $absentDays, float $lwopDays, int $otherMinutes, float $minuteRate, float $basicSalary): array
    {
        $standard = (int) round((float) $this->defaultComputation()->standard_hours_per_day * 60);
        $lwopDays = max(0.0, $lwopDays);
        $absentMinutes = (int) round($absentDays * $standard);
        $lwopMinutes = (int) round($lwopDays * $standard);
        $charges = [
            'absent_minutes' => $absentMinutes,
            'lwop_minutes' => $lwopMinutes,
            'absent_amount' => $absentMinutes * $minuteRate,
            'lwop_amount' => $lwopMinutes * $minuteRate,
            'other_amount' => $otherMinutes * $minuteRate,
        ];
        if (!$cutoffId || ($absentDays <= 0 && $lwopDays <= 0 && $otherMinutes <= 0)) {
            return $charges;
        }

        $days = DB::table('hris.cutoff_summary_days')
            ->where('cutoff_id', $cutoffId)
            ->where('employee_id', $employeeId)
            ->where(function ($query) {
                $query->where('is_absent', 1)->orWhere('lwop_fraction', '>', 0)
                    ->orWhere('late_minutes', '>', 0)->orWhere('undertime_minutes', '>', 0)->orWhere('pass_slip_minutes', '>', 0);
            })
            ->get(['is_absent', 'lwop_fraction', 'required_minutes', 'late_minutes', 'undertime_minutes', 'pass_slip_minutes', 'minute_rate', 'pay_computation_id']);

        $computations = DB::table('hris.pay_computations')->get()->keyBy('id');
        $rateOf = function ($day) use ($computations, $minuteRate, $basicSalary) {
            if ($day->minute_rate !== null) {
                return (float) $day->minute_rate;
            }
            $computation = $computations->get($day->pay_computation_id);

            return $computation
                ? $this->payRates($basicSalary, (float) $computation->working_days_per_month, (float) $computation->standard_hours_per_day)['minute']
                : $minuteRate;
        };
        $minutesOf = fn($day) => (int) $day->required_minutes > 0 ? (int) $day->required_minutes : $standard;

        $absent = $days->where('is_absent', 1);
        if ($absent->count() === (int) round($absentDays)) {
            $charges['absent_minutes'] = (int) $absent->sum($minutesOf);
            $charges['absent_amount'] = $absent->sum(fn($day) => $minutesOf($day) * $rateOf($day));
        }

        $lwop = $days->filter(fn($day) => (float) $day->lwop_fraction > 0);
        if (abs($lwop->sum(fn($day) => (float) $day->lwop_fraction) - $lwopDays) < 0.0005) {
            $minutes = fn($day) => round((float) $day->lwop_fraction * $minutesOf($day));
            $charges['lwop_minutes'] = (int) $lwop->sum($minutes);
            $charges['lwop_amount'] = $lwop->sum(fn($day) => $minutes($day) * $rateOf($day));
        }

        $other = fn($day) => (int) $day->late_minutes + (int) $day->undertime_minutes + (int) $day->pass_slip_minutes;
        if ((int) $days->sum($other) === $otherMinutes) {
            $charges['other_amount'] = $days->sum(fn($day) => $other($day) * $rateOf($day));
        }

        return $charges;
    }

    /**
     * $totalWorkingDays is no longer used for the rate (kept for callers).
     * $lwopDays: approved LWOP days in the cutoff (partial days as fractions) -
     * LWOP dates are not counted in days_absent, so they're deducted here
     * (audit C1). Late/undertime is charged to salary only (client decision
     * 2026-11-11).
     */
    public function savePayrollEmployee($payroll_id, $employee, $totalLateUndertimeMinutes, $totalAbsentDays, $totalWorkingDays, $lwopDays = 0.0)
    {
        $period = $this->getPayrollPeriod($payroll_id);
        $periodStart = $period->cutoff_start_date ?? $period->start_date ?? null;
        $periodEnd = $period->cutoff_end_date ?? $period->end_date ?? null;

        // A cutoff with a snapshot supplies the salary, components, tax rules and
        // pay divisor as they were; older cutoffs read the live tables.
        $snapshot = $this->payrollSnapshot($period->cutoff_id ?? null, (int) $employee['userid']);

        if ($snapshot) {
            $basic_salary = (float) $snapshot['employee']->monthly_salary;
            $divisor = $snapshot['divisor'];
            $division = (int) ($snapshot['employee']->division_id ?? 0);
            $section = (int) ($snapshot['employee']->section_id ?? 0);
        } else {
            $salary = DB::table('hris.payroll_settings')
                ->where('userid', '=', $employee["userid"])
                ->first();
            $office = DB::table('tdh_user.users')->where('id', $employee['userid'])->first(['division', 'section']);
            $basic_salary = $salary->basic_salary ?? 0.00;
            $divisor = null; // the default computation
            $division = (int) ($office->division ?? 0);
            $section = (int) ($office->section ?? 0);
        }

        // ---- Basic Salary & Rates ----
        // The rates saved with the cutoff, when it has them.
        $minute_rate = ($snapshot && $snapshot['employee']->minute_rate !== null)
            ? (float) $snapshot['employee']->minute_rate
            : $this->payRates((float) $basic_salary, $divisor ? (float) $divisor : null)['minute'];
        $charges = $this->scheduledCharges(
            $period->cutoff_id ?? null,
            (int) $employee['userid'],
            (float) $totalAbsentDays,
            (float) $lwopDays,
            (int) $totalLateUndertimeMinutes,
            $minute_rate,
            (float) $basic_salary
        );

        $lateUndertimeDeduction = $charges['other_amount'];
        $absentDeduction = $charges['absent_amount'];
        $lwopDeduction = $charges['lwop_amount'];
        // A month with more than 22 work days could otherwise charge more than the salary.
        $totalTardinessDeductions = min((float) $basic_salary, $lateUndertimeDeduction + $absentDeduction + $lwopDeduction);

        $dynamicComponents = $snapshot
            ? $snapshot['components']
            : $this->getEmployeeDynamicComponents($employee["userid"], $periodStart, $periodEnd, (float) $basic_salary);
        $computedComponents = $this->getComputedComponents($employee, $totalTardinessDeductions);
        $allComponents = $dynamicComponents->merge($computedComponents);
        $dynamicEarnings = $allComponents->where('type', 'earning')->sum('amount');
        $dynamicDeductions = $allComponents->where('type', 'deduction')->sum('amount');

        // ---- Gross Pay: every earning, taxable or not, less tardiness ----
        $gross_pay = ($basic_salary + $dynamicEarnings) - $totalTardinessDeductions;
        $taxableEarnings = $allComponents
            ->where('type', 'earning')
            ->where('taxable', true)
            ->sum('amount');
        // A deduction marked "Before tax" on Payroll Components (the taxable flag)
        // comes off the income that withholding tax is computed on.
        $beforeTaxDeductions = $allComponents
            ->where('type', 'deduction')
            ->where('taxable', true)
            ->sum('amount');
        $taxableGrossPay = ($basic_salary + $taxableEarnings) - $totalTardinessDeductions - $beforeTaxDeductions;

        // ---- Withholding Tax ----
        $taxPurpose = $period->tax_purpose ?? 'Payroll Monthly Salary';
        $annualGross = $taxableGrossPay * 12;
        $withholding_tax = $this->computeWithholdingTax($annualGross, $taxPurpose, $snapshot['tax_rules'] ?? null) / 12;

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
        // Paid in two halves; the second takes any odd centavo so they add up exactly.
        $net_pay = round($gross_pay - $total_deductions, 2);
        $first_half_10th = round($net_pay / 2, 2);
        $second_half_25th = round($net_pay - $first_half_10th, 2);
        // ---- Insert ----
        $payrollEmployeeId = DB::table('hris.payroll_employees')->insertGetId([
            'payroll_id' => $payroll_id,
            'employee_id' => $employee['userid'],
            'division' => $division,
            'section' => $section,
            'basic_salary' => $basic_salary,
            'other_earnings' => $dynamicEarnings,
            'late_absent' => $totalTardinessDeductions,
            'gross_pay' => $gross_pay,
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

    /** The saved pay inputs of one employee in a cutoff, or null if the cutoff has no snapshot for them. */
    private function payrollSnapshot($cutoffId, int $employeeId): ?array
    {
        if (!$cutoffId) {
            return null;
        }
        $employee = DB::table('hris.cutoff_employee_snapshots')
            ->where('cutoff_id', $cutoffId)->where('employee_id', $employeeId)->first();
        if (!$employee) {
            return null;
        }

        $configs = DB::table('hris.cutoff_config_snapshots')
            ->where('cutoff_id', $cutoffId)->whereIn('config_key', ['tax_rules', 'pay_divisor'])
            ->pluck('payload', 'config_key');
        $divisor = json_decode($configs['pay_divisor'] ?? '{}', true);

        return [
            'employee' => $employee,
            'divisor' => (int) ($divisor['working_days_per_month'] ?? self::WORKING_DAYS_PER_MONTH),
            'tax_rules' => collect(json_decode($configs['tax_rules'] ?? '[]')),
            'components' => DB::table('hris.cutoff_payroll_components')
                ->where('cutoff_id', $cutoffId)->where('employee_id', $employeeId)
                ->get(['payroll_component_id', 'code', 'name', 'type', 'taxable', 'amount']),
        ];
    }

    /**
     * Read-only reference for the Append Employees screen: the basic salary and the
     * earnings/deductions this employee's payroll will start from, read the same
     * way savePayrollEmployee does (the cutoff's snapshot when it has one, else the
     * live settings). Withholding tax and tardiness are computed later, so not listed.
     */
    public function employeeComponentReference($payrollId, int $employeeId): array
    {
        $period = $this->getPayrollPeriod($payrollId);
        $periodStart = $period->cutoff_start_date ?? $period->start_date ?? null;
        $periodEnd = $period->cutoff_end_date ?? $period->end_date ?? null;
        $snapshot = $this->payrollSnapshot($period->cutoff_id ?? null, $employeeId);

        if ($snapshot) {
            $basic = (float) $snapshot['employee']->monthly_salary;
            $components = $snapshot['components'];
        } else {
            $basic = (float) (DB::table('hris.payroll_settings')->where('userid', $employeeId)->value('basic_salary') ?? 0);
            $components = $this->getEmployeeDynamicComponents($employeeId, $periodStart, $periodEnd, $basic);
        }

        $order = DB::table('hris.payroll_components')->pluck('sort_order', 'code');
        $rows = collect($components)->map(fn($c) => [
            'code' => $c->code,
            'name' => $c->name,
            'type' => $c->type,
            'taxable' => (bool) $c->taxable,
            'amount' => round((float) $c->amount, 2),
        ])->sortBy(fn($c) => [$order[$c['code']] ?? 999, $c['name']])->values();

        return [
            'source' => $snapshot ? 'snapshot' : 'live',
            'basic_salary' => round($basic, 2),
            'earnings' => $rows->where('type', 'earning')->values(),
            'deductions' => $rows->where('type', 'deduction')->values(),
            'total_earnings' => round($rows->where('type', 'earning')->sum('amount'), 2),
            'total_deductions' => round($rows->where('type', 'deduction')->sum('amount'), 2),
        ];
    }

    public function deletePayrollEmployees($payroll_id)
    {
        // Their earning/deduction lines too (no foreign key removes them), or they are left orphaned.
        $ids = DB::table('hris.payroll_employees')->where('payroll_id', '=', $payroll_id)->pluck('id');
        DB::table('hris.payroll_employee_components')->whereIn('payroll_employee_id', $ids)->delete();

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

    public function getEmployeeDynamicComponents($userId, $periodStart, $periodEnd, float $basicSalary)
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
                'epc.amount',
                'epc.effective_from',
                'epc.effective_to'
            )
            ->get()
            ->filter(function ($row) use ($periodStart, $periodEnd) {
                if ($periodStart && $row->effective_to && $row->effective_to < $periodStart) {
                    return false;
                }

                return !($periodEnd && $row->effective_from && $row->effective_from > $periodEnd);
            })
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
                'name' => 'Basic Pay Adjustment',
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
        $dtrService = app(DTRServices::class);
        $daily = $dtrService->getDailyBreakdown($employeeId, $startDate, $endDate);
        $lwop = $dtrService->getLwopDaysByRange($employeeId, $startDate, $endDate);
        $logIds = $dtrService->getLogIdsByDate($employeeId, $startDate, $endDate);
        $default = $this->defaultComputation();
        $standardMinutes = (int) round((float) $default->standard_hours_per_day * 60);
        $shiftComputations = DB::table('hris.schedule_master_lists')->whereNotNull('pay_computation_id')->pluck('pay_computation_id', 'code');

        DB::table('hris.cutoff_summary_days')
            ->where('cutoff_id', $cutoffId)
            ->where('employee_id', $employeeId)
            ->delete();

        $rows = $daily->map(function ($day, $date) use ($cutoffId, $employeeId, $lwop, $logIds, $default, $standardMinutes, $shiftComputations) {
            $lwopFraction = (float) ($lwop[$date]['days'] ?? 0);

            return [
                'cutoff_id' => $cutoffId,
                'employee_id' => $employeeId,
                'date' => $date,
                'late_minutes' => $day['late_minutes'],
                'undertime_minutes' => $day['undertime_minutes'],
                'is_absent' => $day['is_absent'] ? 1 : 0,
                'is_holiday' => $day['is_holiday'] ? 1 : 0,
                'exemption_reason' => $day['exemption_reason'],
                'attendance_status' => $this->attendanceStatus($day, $lwopFraction),
                'time_in' => $day['first_in'] ?? null,
                'time_out' => $day['last_out'] ?? null,
                'rendered_minutes' => $day['worked_minutes'] ?? null,
                // An unplotted work day has no shift to measure, so it is a standard day.
                'required_minutes' => ($day['required_minutes'] ?? 0)
                    ?: ((!empty($day['is_expected_work_date']) && empty($day['schedule_code'])) ? $standardMinutes : 0),
                'pay_computation_id' => $shiftComputations[$day['schedule_code'] ?? ''] ?? $default->id,
                'schedule_code' => $day['schedule_code'] ?? null,
                'pass_slip_minutes' => $day['pass_slip_minutes'] ?? 0,
                'is_paid' => ($day['is_absent'] || $lwopFraction >= 1.0) ? 0 : 1,
                'is_lwop' => $lwopFraction > 0 ? 1 : 0,
                'lwop_fraction' => $lwopFraction,
                'source_log_ids' => $logIds[$date] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->values()->all();

        if (!empty($rows)) {
            DB::table('hris.cutoff_summary_days')->insert($rows);
        }
    }

    private function attendanceStatus(array $day, float $lwopFraction): string
    {
        if ($lwopFraction >= 1.0) {
            return 'lwop';
        }
        if ($lwopFraction > 0) {
            return 'partial_lwop';
        }
        if ($day['is_absent']) {
            return 'absent';
        }
        if (!empty($day['exemption_reason'])) {
            return $day['exemption_reason'];
        }

        return empty($day['is_expected_work_date']) ? 'rest_day' : 'present';
    }

    /**
     * Records a salary change in employee_salary_history, effective on $effectiveDate
     * (today when not given). Nothing is written if the pay on that date is unchanged.
     */
    public function recordSalaryChange(int $userId, int $grade, int $step, float $basicSalary, ?string $effectiveDate, ?int $changedBy): void
    {
        $date = $effectiveDate ? Carbon::parse($effectiveDate)->toDateString() : now()->toDateString();
        $table = 'hris.employee_salary_history';
        $history = DB::table($table)->where('userid', $userId)->lockForUpdate()->get();

        $previous = $history->filter(fn($h) => !$h->effective_from || $h->effective_from <= $date)
            ->sortBy(fn($h) => (string) $h->effective_from)->last();
        if ($previous && (int) $previous->salary_grade === $grade && (int) $previous->step === $step
            && abs((float) $previous->basic_salary - $basicSalary) < 0.005) {
            return;
        }

        $values = ['salary_grade' => $grade, 'step' => $step, 'basic_salary' => $basicSalary, 'changed_by' => $changedBy, 'updated_at' => now()];
        if ($previous && $previous->effective_from === $date) {
            DB::table($table)->where('id', $previous->id)->update($values);
            return;
        }

        $next = $history->filter(fn($h) => $h->effective_from && $h->effective_from > $date)
            ->sortBy(fn($h) => (string) $h->effective_from)->first();
        if ($previous) {
            DB::table($table)->where('id', $previous->id)->update([
                'effective_to' => Carbon::parse($date)->subDay()->toDateString(),
                'updated_at' => now(),
            ]);
        }

        DB::table($table)->insert($values + [
            'userid' => $userId,
            'effective_from' => $date,
            'effective_to' => $next ? Carbon::parse($next->effective_from)->subDay()->toDateString() : null,
            'source' => 'payroll_settings',
            'created_at' => now(),
        ]);
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
