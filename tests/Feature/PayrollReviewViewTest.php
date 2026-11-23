<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Regular Payroll view's checking aids: subtotals in computation order
 * (Basic Earned, Gross, Taxable Income), deductions grouped into mandatory
 * (before tax) and other, the working behind amounts, and per-row checks and
 * warnings. Every write rolls back.
 */
class PayrollReviewViewTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9781;
    private int $cutoffId;
    private string $purpose = 'BIR Monthly Withholding Tax';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId, 'fname' => 'Review', 'lname' => 'View', 'username' => 'reviewview' . $this->userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52,
        ]);
        DB::table('hris.employees')->where('user_id', $this->userId)->delete();
        DB::table('hris.employees')->insert(['user_id' => $this->userId, 'employee_type' => 'Permanent']);

        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    protected function tearDown(): void
    {
        DB::table('tdh_user.users')->where('id', $this->userId)->delete(); // MyISAM
        parent::tearDown();
    }

    private function seedCutoff(float $salary, array $summary = []): void
    {
        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Review View ' . uniqid(), 'status' => 'finalized', 'snapshot_version' => 1,
            'period_start' => '2027-11-01', 'period_end' => '2027-11-30', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert(array_merge([
            'employee_id' => $this->userId, 'cutoff_id' => $this->cutoffId, 'period_start' => '2027-11-01', 'period_end' => '2027-11-30',
            'total_working_days' => 22, 'days_present' => 22, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0,
            'lwop_days' => 0, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now(),
        ], $summary));
        DB::table('hris.cutoff_employee_snapshots')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'employee_name' => 'VIEW, REVIEW',
            'monthly_salary' => $salary, 'salary_grade' => 15, 'salary_step' => 1, 'payroll_eligible' => 1, 'credit_eligible' => 1,
            'minute_rate' => round($salary / 22 / 8 / 60, 8),
        ]);
        $monthly = [];
        foreach ([[0, 20833, 0], [20833, 33333, 15], [33333, 66667, 20], [66667, 166667, 25], [166667, 666667, 30], [666667, null, 35]] as [$min, $max, $rate]) {
            $monthly[] = ['purpose' => $this->purpose, 'min_annual' => (string) $min, 'max_annual' => $max === null ? null : (string) $max,
                'rate_percent' => (string) $rate, 'tax_mode' => 'progressive', 'basis' => 'monthly'];
        }
        foreach (['pay_divisor' => ['working_days_per_month' => 22], 'tax_rules' => $monthly] as $key => $value) {
            DB::table('hris.cutoff_config_snapshots')->insert([
                'cutoff_id' => $this->cutoffId, 'config_key' => $key, 'payload' => json_encode($value), 'payload_hash' => str_repeat('0', 64),
            ]);
        }
    }

    private function snapshotLine(string $code, string $type, bool $taxable, float $amount): void
    {
        DB::table('hris.cutoff_payroll_components')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'code' => $code, 'name' => $code,
            'type' => $type, 'taxable' => $taxable ? 1 : 0, 'amount' => $amount,
        ]);
    }

    private function computeAndView(): array
    {
        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $this->cutoffId, 'title' => 'Review View Payroll', 'start_date' => '2027-12-01', 'end_date' => '2027-12-31',
            'cutoff_start_date' => '2027-11-01', 'cutoff_end_date' => '2027-11-30', 'payroll_type' => 'Regular',
            'tax_purpose' => $this->purpose, 'status' => 'draft', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.payroll_components')->update(['is_cutoff_adjustable' => 0]);
        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId, 'employees' => [['userid' => $this->userId]],
        ])->assertOk();

        $data = $this->withoutMiddleware()->getJson('/get_payroll_employee?payroll_id=' . $payrollId)->assertOk()->json();
        $rowId = DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->value('id');

        $this->lastPayrollId = $payrollId;

        return [$data, $data['review'][$rowId] ?? null];
    }

    private ?int $lastPayrollId = null;

    /** A computed payroll for the same employee, earlier pay period, with this net pay. */
    private function seedEarlierPayroll(float $netPay): void
    {
        $id = DB::table('hris.payrolls')->insertGetId([
            'title' => 'Earlier Payroll', 'start_date' => '2027-11-01', 'end_date' => '2027-11-30', 'payroll_type' => 'Regular',
            'status' => 'released', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.payroll_employees')->insert([
            'payroll_id' => $id, 'employee_id' => $this->userId, 'division' => 0, 'section' => 0,
            'basic_salary' => 42178, 'net_pay' => $netPay, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fullCutoff(): void
    {
        $this->seedCutoff(42178);
        $this->snapshotLine('PERA', 'earning', false, 2000);
        $this->snapshotLine('HAZARD', 'earning', true, 10544.50);
        $this->snapshotLine('GSIS_PS', 'deduction', true, 3796.02);
        $this->snapshotLine('PHIC', 'deduction', true, 1054.45);
        $this->snapshotLine('HDMF', 'deduction', true, 200);
        $this->snapshotLine('TDHEA', 'deduction', false, 220);
        $this->snapshotLine('GSIS_GS', 'employer_share', false, 5061.36);
    }

    /** @test */
    public function employer_shares_have_their_own_columns_and_total()
    {
        $this->fullCutoff();

        [$data, $review] = $this->computeAndView();

        $this->assertContains('GSIS_GS', collect($data['employerShareComponents'])->pluck('code')->all());
        $this->assertEqualsWithDelta(5061.36, $review['employer_share_total'], 0.005);
    }

    /** @test */
    public function the_summary_shows_the_pay_and_dtr_periods_and_tax_purpose()
    {
        $this->fullCutoff();

        [$data] = $this->computeAndView();

        $this->assertSame('2027-12-01', $data['meta']['start_date']);
        $this->assertSame('2027-11-01', $data['meta']['cutoff_start_date']);
        $this->assertSame($this->purpose, $data['meta']['tax_purpose']);
    }

    /** @test */
    public function each_line_says_where_its_amount_came_from()
    {
        $this->fullCutoff();

        [, $review] = $this->computeAndView();
        $lines = collect($review['lines'])->keyBy('code');

        $this->assertStringContainsString('42,178.00', $review['basic_source']);
        $this->assertEqualsWithDelta(10544.50, $lines['HAZARD']['amount'], 0.005);
        $this->assertNotEmpty($lines['HAZARD']['source']);
        $this->assertStringContainsString('20%', $lines['WITHHOLDING_TAX']['source']);
        $this->assertSame('employer_share', $lines['GSIS_GS']['type']);
    }

    /** @test */
    public function net_pay_is_compared_with_the_last_payroll()
    {
        config(['hris.payroll_net_change_alert_percent' => 10]);
        $this->fullCutoff();
        $this->seedEarlierPayroll(30000);

        [, $review] = $this->computeAndView();

        $this->assertSame('Earlier Payroll', $review['previous']['title']);
        $this->assertEqualsWithDelta(30000, $review['previous']['net_pay'], 0.005);
        $this->assertGreaterThan(10, $review['previous']['change_percent']);
        $this->assertStringContainsString('last payroll', implode(' | ', $review['warnings']));
    }

    /** @test */
    public function the_excel_export_matches_the_view()
    {
        $this->fullCutoff();
        [$data] = $this->computeAndView();

        $path = tempnam(sys_get_temp_dir(), 'payroll') . '.xlsx';
        try {
            $service = app(\App\Services\PayrollServices::class);
            $employees = $service->getPayrollEmployee($this->lastPayrollId);
            $service->exportRegularPayroll($employees, $path, $service->getPayrollPeriod($this->lastPayrollId));

            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $cells = collect($sheet->toArray(null, true, false, false))->flatten()->filter(fn($v) => is_string($v))->map(fn($v) => strtoupper($v));
            foreach (['BASIC EARNED', 'GROSS PAY', 'TAXABLE INCOME', 'TOTAL DEDUCTION', 'NET PAY', 'MANDATORY DEDUCTIONS (BEFORE TAX)', 'EMPLOYER SHARES (NOT DEDUCTED)', 'GSIS_GS'] as $header) {
                $this->assertTrue($cells->contains(fn($v) => strpos($v, $header) !== false), "Export is missing \"{$header}\".");
            }
        } finally {
            @unlink($path);
        }
    }

    /** @test */
    public function the_view_has_subtotals_grouped_deductions_and_the_working()
    {
        $this->seedCutoff(42178);
        $this->snapshotLine('PERA', 'earning', false, 2000);
        $this->snapshotLine('HAZARD', 'earning', true, 10544.50);
        $this->snapshotLine('GSIS_PS', 'deduction', true, 3796.02);
        $this->snapshotLine('PHIC', 'deduction', true, 1054.45);
        $this->snapshotLine('HDMF', 'deduction', true, 200);
        $this->snapshotLine('TDHEA', 'deduction', false, 220);
        $this->snapshotLine('GSIS_GS', 'employer_share', false, 5061.36);

        [$data, $review] = $this->computeAndView();

        $mandatory = collect($data['mandatoryComponents'])->pluck('code');
        $other = collect($data['otherDeductionComponents'])->pluck('code');
        $this->assertTrue($mandatory->contains('GSIS_PS') && $mandatory->contains('PHIC') && $mandatory->contains('HDMF'));
        $this->assertTrue($other->contains('TDHEA'));
        foreach (['WITHHOLDING_TAX', 'GSIS_GS'] as $code) {
            $this->assertFalse($mandatory->contains($code) || $other->contains($code), "{$code} is not a deduction column.");
        }

        $this->assertNotNull($review);
        $this->assertEqualsWithDelta(42178, $review['basic_earned'], 0.005);
        $this->assertEqualsWithDelta(54722.50, $review['gross_pay'], 0.005);
        $this->assertEqualsWithDelta(5050.47, $review['mandatory_total'], 0.005);
        $this->assertEqualsWithDelta(47672.03, $review['taxable_income'], 0.005); // 42,178 + 10,544.50 - 5,050.47
        $this->assertStringContainsString('20%', $review['notes']['withholding_tax']);
        $this->assertStringContainsString('33,333', $review['notes']['withholding_tax']);
        $this->assertTrue($review['checks']['net_balanced']);
        $this->assertTrue($review['checks']['halves_balanced']);
        $this->assertSame([], $review['warnings']);
    }

    /** @test */
    public function problems_are_flagged_with_their_reason()
    {
        config(['hris.payroll_net_pay_floor' => 5000]);
        $this->seedCutoff(5000, ['days_absent' => 2, 'days_present' => 20, 'scheduled_absent_minutes' => 960]);
        $this->snapshotLine('PERA', 'earning', false, 0);
        $peraId = DB::table('hris.payroll_components')->where('code', 'PERA')->value('id');
        DB::table('hris.employee_payroll_components')->where(['userid' => $this->userId, 'payroll_component_id' => $peraId])->delete();
        DB::table('hris.employee_payroll_components')->insert([
            'userid' => $this->userId, 'payroll_component_id' => $peraId, 'amount' => 0, 'amount_override' => 0, 'is_active' => 1, 'is_excluded' => 0,
        ]);

        [, $review] = $this->computeAndView();
        $warnings = implode(' | ', $review['warnings']);

        $this->assertStringContainsString('contribution', $warnings);       // no GSIS / PhilHealth / Pag-IBIG
        $this->assertStringContainsString('PERA', $warnings);               // zero allowance, with why
        $this->assertStringContainsString('Payroll Settings', $review['notes']['components']['PERA']);
        $this->assertStringContainsString('2 absent', $warnings);           // absences charged to pay
        $this->assertStringContainsString('below', $warnings);              // net under the floor
        $this->assertStringContainsString('960', $review['notes']['late_absent']);
    }
}
