<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollServices
{
    public const COMPONENT_TYPES = ['earning', 'deduction', 'employer_share'];

    /** A component type from a request: one of COMPONENT_TYPES, else an earning. */
    public function normalizeComponentType($type): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, self::COMPONENT_TYPES, true) ? $type : 'earning';
    }

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
            $base = preg_replace('/_(ADJUSTMENT|LEAVE_REDUCTION)$/', '', $code);
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

    /**
     * Checking aids for the Regular Payroll view, from the saved payroll lines and
     * the cutoff snapshot (nothing is recomputed from the DTR):
     *  - deduction columns split into mandatory (before-tax lines: GSIS PS,
     *    PhilHealth, Pag-IBIG...) and other; every active before-tax deduction in
     *    the catalog gets a column even when nobody has it, so a missing
     *    contribution shows as 0;
     *  - per employee: Basic Earned, Gross, mandatory total, Taxable Income, the
     *    working behind Late/Absent, the tax and zero allowances, row checks and
     *    warnings.
     *
     * @return array{mandatoryComponents: \Illuminate\Support\Collection, otherDeductionComponents: \Illuminate\Support\Collection, review: array}
     */
    public function payrollReview($payroll, $payrollEmployees): array
    {
        $employees = collect($payrollEmployees);
        $lines = DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', $employees->pluck('id'))
            ->get(['payroll_employee_id', 'code', 'name', 'type', 'taxable', 'amount'])
            ->groupBy('payroll_employee_id');
        $catalog = DB::table('hris.payroll_components')->get()->keyBy('code');

        // Deduction columns, grouped by the before-tax flag saved on the lines.
        $deductions = $lines->flatten(1)->where('type', 'deduction')->where('code', '<>', 'WITHHOLDING_TAX');
        $beforeTaxCodes = $deductions->where('taxable', 1)->pluck('code')
            ->merge($catalog->where('type', 'deduction')->where('taxable', 1)->where('is_active', 1)->keys())
            ->unique();
        $columnOf = fn($code) => (object) [
            'code' => $code,
            'name' => optional($deductions->firstWhere('code', $code))->name ?? optional($catalog->get($code))->name ?? $code,
            'type' => 'deduction',
        ];
        $order = fn($column) => [(int) optional($catalog->get(preg_replace('/_ADJUSTMENT$/', '', $column->code)))->sort_order, $column->code];
        $mandatory = $beforeTaxCodes->map($columnOf)->sortBy($order)->values();
        $other = $deductions->pluck('code')->unique()->diff($beforeTaxCodes)->map($columnOf)->sortBy($order)->values();

        // Employer shares (GSIS GS, EC): their own columns, never part of the employee's pay.
        $shares = $lines->flatten(1)->where('type', 'employer_share');
        $employerShares = $shares->pluck('code')
            ->merge($catalog->where('type', 'employer_share')->where('is_active', 1)->keys())
            ->unique()
            ->map(fn($code) => (object) [
                'code' => $code,
                'name' => optional($shares->firstWhere('code', $code))->name ?? optional($catalog->get($code))->name ?? $code,
                'type' => 'employer_share',
            ])->sortBy($order)->values();

        // Cutoff data behind the amounts.
        $cutoffId = $payroll->cutoff_id ?? null;
        $summaries = $cutoffId ? DB::table($this->resolveCutoffSummaryTable())->where('cutoff_id', $cutoffId)
            ->whereIn('employee_id', $employees->pluck('employee_id'))->get()->keyBy('employee_id') : collect();
        $snapshots = $cutoffId ? DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $cutoffId)
            ->whereIn('employee_id', $employees->pluck('employee_id'))->get()->keyBy('employee_id') : collect();
        $taxRules = $cutoffId ? DB::table('hris.cutoff_config_snapshots')->where(['cutoff_id' => $cutoffId, 'config_key' => 'tax_rules'])->value('payload') : null;
        $taxRules = $taxRules !== null ? collect(json_decode($taxRules)) : null;
        $purpose = $payroll->tax_purpose ?? 'Payroll Monthly Salary';

        // Current settings, only to explain a zero allowance.
        $settings = DB::table('hris.employee_payroll_components')->whereIn('userid', $employees->pluck('employee_id'))
            ->get()->groupBy('userid');
        $types = DB::table('hris.employees')->whereIn('user_id', $employees->pluck('employee_id'))->pluck('employee_type', 'user_id');
        $earningCodes = $lines->flatten(1)->where('type', 'earning')->pluck('code')->unique();
        $floor = (float) config('hris.payroll_net_pay_floor', 5000);
        $alertPercent = (float) config('hris.payroll_net_change_alert_percent', 10);
        $money = fn($value) => number_format((float) $value, 2);

        // Each line's saved computation basis (e.g. a leave reduction) from the cutoff snapshot.
        $bases = $cutoffId ? DB::table('hris.cutoff_payroll_components')->where('cutoff_id', $cutoffId)
            ->whereIn('employee_id', $employees->pluck('employee_id'))->whereNotNull('computation_basis')
            ->get(['employee_id', 'code', 'computation_basis'])->groupBy('employee_id') : collect();

        // The employee's latest payroll for an earlier pay period, for the net pay comparison.
        $previous = empty($payroll->start_date) ? collect() : DB::table('hris.payroll_employees as pe')
            ->join('hris.payrolls as p', 'p.id', '=', 'pe.payroll_id')
            ->whereIn('pe.employee_id', $employees->pluck('employee_id'))
            ->where('p.id', '<>', (int) ($payroll->id ?? 0))
            ->where('p.start_date', '<', $payroll->start_date)
            ->orderByDesc('p.start_date')->orderByDesc('p.id')
            ->get(['pe.employee_id', 'pe.net_pay', 'p.title', 'p.start_date', 'p.end_date'])
            ->groupBy('employee_id')->map(fn($rows) => $rows->first());

        $review = [];
        foreach ($employees as $employee) {
            $own = $lines->get($employee->id, collect());
            $amounts = $own->groupBy('code')->map(fn($same) => round($same->sum('amount'), 2));
            $basic = (float) $employee->basic_salary;
            $lateAbsent = (float) $employee->late_absent;
            $mandatoryTotal = round($own->where('type', 'deduction')->where('taxable', 1)->sum('amount'), 2);
            $taxableEarnings = $own->where('type', 'earning')->where('taxable', 1)->sum('amount');
            $taxable = round($basic + $taxableEarnings - $lateAbsent - $mandatoryTotal, 2);
            $tax = (float) ($amounts['WITHHOLDING_TAX'] ?? 0);
            $summary = $summaries->get($employee->employee_id);
            $snapshot = $snapshots->get($employee->employee_id);

            $notes = ['components' => []];
            $warnings = [];

            // Late / absent / LWOP working.
            if ($summary) {
                $rate = $snapshot && $snapshot->minute_rate !== null ? (float) $snapshot->minute_rate : null;
                $parts = [];
                if ((int) $summary->days_absent > 0) {
                    $parts[] = "Absent {$summary->days_absent} day(s)" . ($summary->scheduled_absent_minutes ? " ({$summary->scheduled_absent_minutes} min)" : '');
                }
                if ((float) $summary->lwop_days > 0) {
                    $parts[] = 'LWOP ' . sprintf('%g', (float) $summary->lwop_days) . ' day(s)' . ($summary->scheduled_lwop_minutes ? " ({$summary->scheduled_lwop_minutes} min)" : '');
                }
                foreach (['late_minutes' => 'Late', 'undertime_minutes' => 'Undertime', 'approved_pass_slips_minutes' => 'Personal pass slip'] as $field => $label) {
                    if ((int) ($summary->$field ?? 0) > 0) {
                        $parts[] = "{$label} {$summary->$field} min";
                    }
                }
                $notes['late_absent'] = empty($parts)
                    ? 'No absence, LWOP, late, undertime or pass slip in the cutoff.'
                    : implode(' · ', $parts) . ($rate ? ' — each minute at ₱' . number_format($rate, 5) . ' (day = shift length)' : '');
                if ((int) $summary->days_absent > 0) {
                    $warnings[] = "{$summary->days_absent} absent day(s) with no leave filed are charged to pay.";
                }
            }

            $notes['withholding_tax'] = $this->describeWithholdingTax($taxable, $purpose, $taxRules);

            // Allowances that came out 0 or are missing for this employee.
            foreach ($earningCodes as $code) {
                if (abs((float) ($amounts[$code] ?? 0)) >= 0.005 || preg_match('/_(ADJUSTMENT|LEAVE_REDUCTION)$/', $code)) {
                    continue;
                }
                $component = $catalog->get($code);
                $setting = collect($settings->get($employee->employee_id))->firstWhere('payroll_component_id', optional($component)->id);
                if ($setting && $setting->is_excluded) {
                    $reason = 'Excluded in Payroll Settings';
                } elseif ($setting && $setting->amount_override !== null && (float) $setting->amount_override == 0.0) {
                    $reason = 'Set to 0 in Payroll Settings';
                } elseif ($setting && $setting->rate_percent_override !== null && (float) $setting->rate_percent_override == 0.0) {
                    $reason = 'Rate overridden to 0% in Payroll Settings';
                } elseif ($component && $component->permanent_only && ($types[$employee->employee_id] ?? null) !== 'Permanent') {
                    $reason = 'Permanent employees only (employee type: ' . ($types[$employee->employee_id] ?? 'blank') . ')';
                } elseif ($component && !$component->is_global) {
                    $reason = 'Not assigned to this employee';
                } else {
                    $reason = 'No amount in this cutoff';
                }
                $notes['components'][$code] = $reason;
                $warnings[] = (optional($component)->name ? "{$component->name} ({$code})" : $code) . " is 0: {$reason}.";
            }

            if ($mandatoryTotal <= 0) {
                $warnings[] = 'No mandatory contributions (GSIS / PhilHealth / Pag-IBIG) encoded.';
            }
            if ((float) $employee->net_pay < $floor) {
                $warnings[] = 'Net pay ₱' . $money($employee->net_pay) . ' is below ₱' . $money($floor) . '.';
            }

            // Net pay against the last payroll.
            $last = $previous->get($employee->employee_id);
            $comparison = null;
            if ($last) {
                $change = round((float) $employee->net_pay - (float) $last->net_pay, 2);
                $percent = (float) $last->net_pay != 0.0 ? round($change / (float) $last->net_pay * 100, 2) : null;
                $comparison = ['title' => $last->title, 'start_date' => $last->start_date, 'end_date' => $last->end_date,
                    'net_pay' => round((float) $last->net_pay, 2), 'change' => $change, 'change_percent' => $percent];
                if ($percent !== null && abs($percent) > $alertPercent) {
                    $warnings[] = 'Net pay changed ' . ($change > 0 ? '+' : '') . $percent . '% from the last payroll ("' . $last->title . '": ₱' . $money($last->net_pay) . ').';
                }
            }

            // Every line with where its amount came from, for the employee breakdown.
            $ownBases = collect($bases->get($employee->employee_id))->pluck('computation_basis', 'code');
            $ownSettings = collect($settings->get($employee->employee_id))->keyBy('payroll_component_id');
            $breakdown = $own->map(function ($line) use ($catalog, $ownBases, $ownSettings, $notes) {
                return [
                    'code' => $line->code,
                    'name' => $line->name ?: $line->code,
                    'type' => $line->type,
                    'taxable' => (bool) $line->taxable,
                    'amount' => round((float) $line->amount, 2),
                    'source' => $this->lineSource($line, $catalog->get($line->code), $ownSettings, $ownBases, $notes),
                ];
            })->values();
            $basicSource = $snapshot
                ? 'Monthly basic ₱' . $money($snapshot->monthly_salary) . ' (SG ' . ($snapshot->salary_grade ?? '?') . '-' . ($snapshot->salary_step ?? '?') . ') saved with the cutoff'
                    . ($snapshot->salary_source ? ', from ' . $snapshot->salary_source : '')
                    . ($snapshot->salary_effective_date ? ', effective ' . $snapshot->salary_effective_date : '')
                : 'Monthly basic ₱' . $money($employee->basic_salary) . ' from Payroll Settings (no cutoff snapshot)';

            $review[$employee->id] = [
                'basic_earned' => round($basic - $lateAbsent, 2),
                'gross_pay' => round((float) $employee->gross_pay, 2),
                'mandatory_total' => $mandatoryTotal,
                'taxable_income' => $taxable,
                'other_deductions_total' => round((float) $employee->deductions - $mandatoryTotal - $tax, 2),
                'checks' => [
                    'net_balanced' => abs(round((float) $employee->gross_pay - (float) $employee->deductions, 2) - (float) $employee->net_pay) < 0.015,
                    'halves_balanced' => abs((float) $employee->first_half_10th + (float) $employee->second_half_25th - (float) $employee->net_pay) < 0.015,
                ],
                'employer_share_total' => round($own->where('type', 'employer_share')->sum('amount'), 2),
                'previous' => $comparison,
                'basic_source' => $basicSource,
                'lines' => $breakdown,
                'notes' => $notes,
                'warnings' => $warnings,
            ];
        }

        return [
            'mandatoryComponents' => $mandatory,
            'otherDeductionComponents' => $other,
            'employerShareComponents' => $employerShares,
            'meta' => [
                'title' => $payroll->title ?? null,
                'status' => $payroll->status ?? null,
                'start_date' => $payroll->start_date ?? null,
                'end_date' => $payroll->end_date ?? null,
                'cutoff_title' => $payroll->cutoff_title ?? null,
                'cutoff_start_date' => $payroll->cutoff_start_date ?? null,
                'cutoff_end_date' => $payroll->cutoff_end_date ?? null,
                'tax_purpose' => $purpose,
                'net_change_alert_percent' => $alertPercent,
            ],
            'review' => $review,
        ];
    }

    /** Where a payroll line's amount came from, in words, for the employee breakdown. */
    private function lineSource(object $line, ?object $component, $settings, $bases, array $notes): string
    {
        $setting = $component ? $settings->get($component->id) : null;

        if ($line->code === 'WITHHOLDING_TAX') {
            return $notes['withholding_tax'];
        }
        if ($bases->has($line->code)) {
            return $bases[$line->code];
        }
        if (preg_match('/_ADJUSTMENT$/', $line->code)) {
            return 'Cutoff adjustment entered when the payroll was computed';
        }
        if ($setting && $setting->amount_override !== null) {
            return 'Amount set for this employee in Payroll Settings';
        }
        if ($setting && $setting->rate_percent_override !== null) {
            return sprintf('%g', (float) $setting->rate_percent_override) . '% of basic salary (rate set for this employee)';
        }
        if ($component && $component->calculation_type === 'percentage') {
            return sprintf('%g', (float) $component->rate_percent) . '% of ' . ($component->base_type === 'component' ? $component->base_component_code : 'basic salary');
        }
        if ($component && $component->is_global) {
            return 'Default amount for everyone (Earnings & Deductions)';
        }

        return 'Saved with the cutoff snapshot';
    }

    /** The bracket and formula a withholding tax came from, for the view's tooltip. */
    private function describeWithholdingTax(float $taxable, string $purpose, $snapshotRules = null): string
    {
        $rules = $snapshotRules !== null
            ? collect($snapshotRules)->where('purpose', $purpose)->sortBy(fn($rule) => (float) $rule->min_annual)->values()
            : DB::table('hris.payroll_tax_rules')->where('is_active', 1)->where('purpose', $purpose)->orderBy('min_annual')->get();
        if ($rules->isEmpty()) {
            return "No active tax rules for \"{$purpose}\".";
        }

        $monthly = ($rules->first()->basis ?? 'annual') === 'monthly';
        $amount = $monthly ? $taxable : $taxable * 12;
        $money = fn($value) => number_format((float) $value, 2);
        $lead = "{$purpose} (" . ($monthly ? 'monthly table' : 'annual table: ₱' . $money($taxable) . ' × 12 = ₱' . $money($amount)) . '): ';

        if ($rules->first()->tax_mode !== 'progressive') {
            $rule = $rules->filter(fn($r) => $amount >= (float) $r->min_annual && ($r->max_annual === null || $amount <= (float) $r->max_annual))->last();
            return $lead . ($rule ? sprintf('%g', (float) $rule->rate_percent) . '% × ₱' . $money($amount) : 'no bracket matches');
        }

        $base = 0.0;
        $bracket = null;
        foreach ($rules as $rule) {
            $min = (float) $rule->min_annual;
            $max = $rule->max_annual !== null ? (float) $rule->max_annual : INF;
            if ($amount <= $min) {
                break;
            }
            $bracket = $rule;
            if ($amount > $max) {
                $base += ($max - $min) * (float) $rule->rate_percent / 100;
            }
        }
        if (!$bracket || (float) $bracket->rate_percent == 0.0) {
            return $lead . '₱' . $money($amount) . ' is within the tax-exempt bracket.';
        }

        $text = '₱' . $money($base) . ' + ' . sprintf('%g', (float) $bracket->rate_percent) . '% × (₱' . $money($amount) . ' − ₱' . $money($bracket->min_annual) . ')';

        return $lead . $text . ($monthly ? '' : ', ÷ 12');
    }

    /**
     * Payroll Summary workbook laid out like the Regular Payroll view: columns in
     * computation order with subtotals (Basic Earned, Gross Pay, Taxable Income,
     * Total Deduction, Net Pay), deductions grouped mandatory / loans & other,
     * employer shares apart, a totals row for every money column, and the row
     * checks and warnings - so the printed copy can be checked the same way.
     */
    public function exportRegularPayroll($payrollEmployees, $exportPath, $payroll = null)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Summary');

        $breakdown = $this->payrollComponentColumns($payrollEmployees);
        $review = $payroll ? $this->payrollReview($payroll, $payrollEmployees)
            : ['mandatoryComponents' => collect(), 'otherDeductionComponents' => collect($breakdown['deduction']), 'employerShareComponents' => collect(), 'review' => []];
        $amount = fn($item, $code) => (float) (($breakdown['amounts']->get($item->id) ?? collect())[$code] ?? 0);
        $rev = fn($item) => $review['review'][$item->id] ?? [];

        // [group, header, value, kind]: kind is text, plain (centred), money or subtotal (both totalled).
        $columns = [
            [null, 'No.', null, 'plain'],
            [null, 'NAME', fn($item) => $this->formatEmployeeName($item), 'text'],
            [null, 'FAMILY NAME', fn($item) => $item->lname ?? '', 'text'],
            [null, 'FIRST NAME', fn($item) => $item->fname ?? '', 'text'],
            [null, 'MIDDLE NAME', fn($item) => $item->mname ?? '', 'text'],
            [null, 'SUFFIX', fn($item) => $item->suffix ?? '', 'text'],
            [null, 'MI', fn($item) => !empty($item->mname) ? strtoupper(substr($item->mname, 0, 1)) . '.' : '', 'text'],
            [null, 'DIVISION', fn($item) => $item->division ?? '', 'text'],
            [null, 'DESIGNATION', fn($item) => $item->designation ?? '', 'text'],
            [null, 'zz', fn($item) => $this->generateAcronym($item->designation ?? ''), 'text'],
            [null, 'SG', fn($item) => $item->salary_grade ?? '', 'plain'],
            [null, 'Step', fn($item) => $item->step ?? '', 'plain'],
            ['BASIC', 'BASIC PAY', fn($item) => (float) $item->basic_salary, 'money'],
            ['BASIC', 'LESS: LATE / ABSENT / LWOP', fn($item) => (float) $item->late_absent, 'money'],
            ['BASIC', 'BASIC EARNED', fn($item) => (float) ($rev($item)['basic_earned'] ?? ((float) $item->basic_salary - (float) $item->late_absent)), 'subtotal'],
        ];
        foreach ($breakdown['earning'] as $component) {
            $columns[] = ['EARNINGS', strtoupper($component->name), fn($item) => $amount($item, $component->code), 'money'];
        }
        $columns[] = [null, 'GROSS PAY', fn($item) => (float) $item->gross_pay, 'subtotal'];
        foreach ($review['mandatoryComponents'] as $component) {
            $columns[] = ['MANDATORY DEDUCTIONS (BEFORE TAX)', strtoupper($component->name), fn($item) => $amount($item, $component->code), 'money'];
        }
        $columns[] = [null, 'TAXABLE INCOME', fn($item) => (float) ($rev($item)['taxable_income'] ?? 0), 'subtotal'];
        $columns[] = [null, 'WITHHOLDING TAX', fn($item) => $amount($item, 'WITHHOLDING_TAX'), 'money'];
        foreach ($review['otherDeductionComponents'] as $component) {
            $columns[] = ['LOANS & OTHER DEDUCTIONS', strtoupper($component->name), fn($item) => $amount($item, $component->code), 'money'];
        }
        $columns[] = [null, 'TOTAL DEDUCTION', fn($item) => (float) $item->deductions, 'subtotal'];
        $columns[] = [null, 'NET PAY', fn($item) => (float) $item->net_pay, 'subtotal'];
        $columns[] = ['PAY DATES', '1ST HALF 10TH', fn($item) => (float) $item->first_half_10th, 'money'];
        $columns[] = ['PAY DATES', '2ND HALF 25TH', fn($item) => (float) $item->second_half_25th, 'money'];
        foreach ($review['employerShareComponents'] as $component) {
            $columns[] = ['EMPLOYER SHARES (NOT DEDUCTED)', strtoupper($component->name), fn($item) => $amount($item, $component->code), 'money'];
        }
        $columns[] = [null, 'NET VS LAST PAYROLL', fn($item) => isset($rev($item)['previous']['change']) ? (float) $rev($item)['previous']['change'] : '', 'plainmoney'];
        $columns[] = [null, 'CHECK / WARNINGS', function ($item) use ($rev) {
            $r = $rev($item);
            $problems = [];
            if (($r['checks']['net_balanced'] ?? true) === false) $problems[] = 'Gross - Total Deduction does not equal Net Pay.';
            if (($r['checks']['halves_balanced'] ?? true) === false) $problems[] = '10th + 25th does not equal Net Pay.';
            $problems = array_merge($problems, $r['warnings'] ?? []);

            return empty($problems) ? 'OK' : implode("\n", $problems);
        }, 'text'];

        $letter = fn(int $index) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        $lastColLetter = $letter(count($columns) - 1);
        $groupRow = 5;
        $headerRow = 6;
        $dataStartRow = 7;
        $center = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;

        // Title and the summary lines a reviewer checks first.
        $lines = ['PAYROLL SUMMARY' . (!empty($payroll->title) ? ' - ' . strtoupper($payroll->title) : '')];
        if (!empty($payroll->start_date) && !empty($payroll->end_date)) {
            $lines[] = 'Pay period: ' . Carbon::parse($payroll->start_date)->format('F j') . ' to ' . Carbon::parse($payroll->end_date)->format('F j, Y')
                . (!empty($payroll->cutoff_start_date) ? '   |   DTR (cutoff): ' . Carbon::parse($payroll->cutoff_start_date)->format('F j') . ' to ' . Carbon::parse($payroll->cutoff_end_date)->format('F j, Y') : '');
        }
        $lines[] = 'Tax purpose: ' . ($payroll->tax_purpose ?? 'Payroll Monthly Salary') . '   |   Status: ' . ucfirst(str_replace('_', ' ', (string) ($payroll->status ?? ''))) . '   |   Employees: ' . count($payrollEmployees);
        foreach ($lines as $i => $text) {
            $r = $i + 1;
            $sheet->setCellValue("A{$r}", $text);
            $sheet->mergeCells("A{$r}:{$lastColLetter}{$r}");
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal($center);
            $sheet->getStyle("A{$r}")->getFont()->setBold($i === 0)->setSize($i === 0 ? 14 : 10)->setItalic($i > 0);
        }

        // Two header rows: group names (merged across their columns), then column names.
        for ($index = 0; $index < count($columns);) {
            $group = $columns[$index][0];
            $span = 1;
            while ($group !== null && $index + $span < count($columns) && $columns[$index + $span][0] === $group) {
                $span++;
            }
            $from = $letter($index);
            if ($group === null) {
                $sheet->setCellValue("{$from}{$groupRow}", $columns[$index][1]);
                $sheet->mergeCells("{$from}{$groupRow}:{$from}{$headerRow}");
            } else {
                $sheet->setCellValue("{$from}{$groupRow}", $group);
                if ($span > 1) {
                    $sheet->mergeCells("{$from}{$groupRow}:" . $letter($index + $span - 1) . $groupRow);
                }
                for ($j = $index; $j < $index + $span; $j++) {
                    $sheet->setCellValue($letter($j) . $headerRow, $columns[$j][1]);
                }
            }
            $index += $span;
        }

        $row = $dataStartRow;
        foreach (collect($payrollEmployees)->values() as $number => $item) {
            foreach ($columns as $index => [, , $value]) {
                $sheet->setCellValue($letter($index) . $row, $value === null ? $number + 1 : $value($item));
            }
            $row++;
        }

        $lastDataRow = $row - 1;
        $totalsRow = $row;
        $sheet->setCellValue("A{$totalsRow}", 'TOTAL (' . count($payrollEmployees) . ' employees)');
        $firstMoney = collect($columns)->search(fn($column) => in_array($column[3], ['money', 'subtotal'], true));
        $sheet->mergeCells("A{$totalsRow}:" . $letter(max(0, $firstMoney - 1)) . $totalsRow);
        foreach ($columns as $index => [, , , $kind]) {
            if (in_array($kind, ['money', 'subtotal'], true) && $lastDataRow >= $dataStartRow) {
                $col = $letter($index);
                $sheet->setCellValue("{$col}{$totalsRow}", "=SUM({$col}{$dataStartRow}:{$col}{$lastDataRow})");
            }
        }

        // Header styling
        $headerRange = "A{$groupRow}:{$lastColLetter}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal($center)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        // Totals row styling
        $totalsRange = "A{$totalsRow}:{$lastColLetter}{$totalsRow}";
        $sheet->getStyle($totalsRange)->getFont()->setBold(true);
        $sheet->getStyle($totalsRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');

        $sheet->getStyle("A{$groupRow}:{$lastColLetter}{$totalsRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Per-column alignment, number format, subtotal shading, employer shares greyed.
        foreach ($columns as $index => [$group, , , $kind]) {
            $col = $letter($index);
            $range = "{$col}{$dataStartRow}:{$col}{$totalsRow}";
            if ($kind === 'text') {
                $sheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)->setWrapText(true);
            } elseif ($kind === 'plain') {
                $sheet->getStyle($range)->getAlignment()->setHorizontal($center);
            } else {
                $sheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            if ($kind === 'subtotal') {
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")->getFont()->setBold(true);
            }
            if ($group === 'EMPLOYER SHARES (NOT DEDUCTED)') {
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")->getFont()->getColor()->setRGB('64748B');
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

    /**
     * Monthly withholding tax on a month's taxable compensation, by the purpose's
     * rules. Monthly-basis rules (the BIR monthly table) apply to it as-is;
     * annual-basis rules apply to it x 12, and the result is divided by 12.
     * Rules saved before the basis existed are annual.
     *
     * @param \Illuminate\Support\Collection|null $snapshotRules tax rules saved with the cutoff; null reads the live table
     */
    public function withholdingTax(float $monthlyTaxable, string $purpose, $snapshotRules = null): float
    {
        $rules = $snapshotRules !== null
            ? collect($snapshotRules)->where('purpose', $purpose)->sortBy(fn($rule) => (float) $rule->min_annual)->values()
            : DB::table('hris.payroll_tax_rules')->where('is_active', 1)->where('purpose', $purpose)->orderBy('min_annual')->get();
        if ($rules->isEmpty()) {
            return 0.0;
        }

        $basisOf = fn($rule) => ($rule->basis ?? 'annual') === 'monthly' ? 'monthly' : 'annual';
        $basis = $basisOf($rules->first());
        if ($rules->contains(fn($rule) => $basisOf($rule) !== $basis)) {
            // Data anomaly (savePayrollTaxRules blocks it): use the lowest bracket's basis.
            \Illuminate\Support\Facades\Log::warning("Payroll tax rules for purpose \"{$purpose}\" mix annual and monthly bases; using {$basis}.");
            $rules = $rules->filter(fn($rule) => $basisOf($rule) === $basis)->values();
        }

        return $basis === 'monthly'
            ? (float) $this->computeWithholdingTax($monthlyTaxable, $purpose, $rules)
            : (float) $this->computeWithholdingTax($monthlyTaxable * 12, $purpose, $rules) / 12;
    }

    /** Tax on $annualGross - or on any amount in the rules' own basis - by the purpose's brackets. */
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

        $bases = collect($activeRules)->map(fn($rule) => ($rule['basis'] ?? 'annual') === 'monthly' ? 'monthly' : 'annual')->unique();
        if ($bases->count() > 1) {
            return "Tax rules for purpose \"{$purpose}\" mix annual and monthly basis. All active rules for a purpose must use the same basis.";
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
        $withholding_tax = $this->withholdingTax($taxableGrossPay, $taxPurpose, $snapshot['tax_rules'] ?? null);

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
            // Not taken from pay; listed for reference only.
            'employer_shares' => $rows->where('type', 'employer_share')->values(),
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

    /**
     * Employee types a permanent_only component (the Magna Carta benefits) is
     * paid to; hris.employees.employee_type.
     */
    public const PERMANENT_EMPLOYEE_TYPES = ['Permanent'];

    public function getEmployeeDynamicComponents($userId, $periodStart, $periodEnd, float $basicSalary)
    {
        $isPermanent = in_array(
            DB::table('hris.employees')->where('user_id', $userId)->value('employee_type'),
            self::PERMANENT_EMPLOYEE_TYPES,
            true
        );
        $eligible = fn($row) => !$row->permanent_only || $isPermanent;

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
                'pc.permanent_only',
                'pc.absence_reduction',
                'pc.absence_reduction_rate',
                'epc.amount_override',
                'epc.rate_percent_override',
                'epc.is_active as employee_active',
                'epc.is_excluded',
                'epc.effective_from',
                'epc.effective_to'
            )
            ->get()
            ->filter($eligible)
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
                    'absence_reduction' => $row->absence_reduction ?? 'none',
                    'absence_reduction_rate' => $row->absence_reduction_rate,
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
                'pc.permanent_only',
                'pc.absence_reduction',
                'pc.absence_reduction_rate',
                'epc.amount_override',
                'epc.amount',
                'epc.effective_from',
                'epc.effective_to'
            )
            ->get()
            ->filter($eligible)
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
                    'absence_reduction' => $row->absence_reduction ?? 'none',
                    'absence_reduction_rate' => $row->absence_reduction_rate,
                ];
            });

        return $globals->merge($employeeSpecific);
    }

    /**
     * The reduction of each earning for days the employee was not on duty -
     * paid leave, absences and LWOP - by the rule HR set on its component:
     * 'prorate' takes (amount / working days per month) a day, 'per_day' a
     * fixed amount a day. One negative earning line per reduced component,
     * never more than the component itself, with the same tax treatment.
     *
     * @param \Illuminate\Support\Collection $components objects from getEmployeeDynamicComponents()
     * @return \Illuminate\Support\Collection objects shaped like those components, plus computation_basis
     */
    public function absenceReductions(\Illuminate\Support\Collection $components, float $daysOff, float $workingDaysPerMonth): \Illuminate\Support\Collection
    {
        if ($daysOff <= 0) {
            return collect();
        }

        return $components
            ->filter(fn($component) => $component->type === 'earning' && (float) $component->amount > 0
                && in_array($component->absence_reduction ?? 'none', ['prorate', 'per_day'], true))
            ->map(function ($component) use ($daysOff, $workingDaysPerMonth) {
                $amount = (float) $component->amount;
                $perDay = ($component->absence_reduction === 'prorate')
                    ? ($workingDaysPerMonth > 0 ? $amount / $workingDaysPerMonth : 0.0)
                    : (float) ($component->absence_reduction_rate ?? 0);
                $reduction = round(min($amount, $perDay * $daysOff), 2);
                $days = rtrim(rtrim(number_format($daysOff, 3, '.', ''), '0'), '.');

                return (object) [
                    'payroll_component_id' => $component->payroll_component_id,
                    'code' => $component->code . '_LEAVE_REDUCTION',
                    'name' => $component->name . ' (Leave/Absence Reduction)',
                    'type' => 'earning',
                    'taxable' => (bool) $component->taxable,
                    'amount' => -$reduction,
                    'computation_basis' => $component->absence_reduction === 'prorate'
                        ? "{$days} day(s) off x " . number_format($amount, 2) . " / " . rtrim(rtrim(number_format($workingDaysPerMonth, 2, '.', ''), '0'), '.') . " days"
                        : "{$days} day(s) off x " . number_format($perDay, 2) . " a day",
                ];
            })
            ->filter(fn($line) => $line->amount < 0)
            ->values();
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
            ->get(['code', 'name', 'type', 'taxable', 'sort_order']);
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
                // Basic pay differentials are compensation: taxed like basic salary.
                'taxable' => true,
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

            // Same tax treatment as the component it adjusts: arrears of a
            // before-tax contribution (GSIS PS, PhilHealth, Pag-IBIG) also come
            // off before tax, and an adjustment to a taxable earning is taxed.
            $components[] = (object) [
                'payroll_component_id' => null,
                'code' => $component->code . '_ADJUSTMENT',
                'name' => $component->name . ' (Cutoff Adjustment)',
                'type' => $component->type,
                'taxable' => (bool) $component->taxable,
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
