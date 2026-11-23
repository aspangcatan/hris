<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Employee shares of GSIS, PhilHealth and Pag-IBIG are excluded from taxable
 * compensation (NIRC Sec. 32(B)(7)(f), as amended by RA 10963), so they come
 * off before withholding tax (audit T4). Employer shares are not the
 * employee's deduction and are left alone. Every write rolls back.
 */
class PayrollMandatoryContributionTaxTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9741;
    private int $cutoffId;
    private string $purpose = 'Payroll Monthly Salary';
    private string $testCode = 'TEST_BT_CONTRIB';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId, 'fname' => 'Contrib', 'lname' => 'Tax', 'username' => 'contribtax' . $this->userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52,
        ]);

        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function migration()
    {
        return require base_path('database/migrations/2026_11_23_000000_mark_mandatory_contributions_before_tax.php');
    }

    /** @test */
    public function the_migration_marks_employee_contribution_shares_before_tax_only()
    {
        DB::table('hris.payroll_components')
            ->whereIn('code', ['GSIS_PS', 'PHIC', 'HDMF', 'GSIS_GS', 'GSIS_EC'])
            ->update(['taxable' => 0]);

        $this->migration()->up();

        $flags = DB::table('hris.payroll_components')
            ->whereIn('code', ['GSIS_PS', 'PHIC', 'HDMF', 'GSIS_GS', 'GSIS_EC'])
            ->pluck('taxable', 'code');

        foreach (['GSIS_PS', 'PHIC', 'HDMF'] as $code) {
            if ($flags->has($code)) {
                $this->assertSame(1, (int) $flags[$code], "{$code} should be taken before tax.");
            }
        }
        // Employer shares are not the employee's deduction: unchanged.
        foreach (['GSIS_GS', 'GSIS_EC'] as $code) {
            if ($flags->has($code)) {
                $this->assertSame(0, (int) $flags[$code], "{$code} should not be touched.");
            }
        }
    }

    private function seedCutoff(float $salary, array $taxRules): void
    {
        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Contribution Tax ' . uniqid(), 'status' => 'finalized', 'snapshot_version' => 1,
            'period_start' => '2027-08-01', 'period_end' => '2027-08-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $this->cutoffId, 'period_start' => '2027-08-01', 'period_end' => '2027-08-31',
            'total_working_days' => 22, 'days_present' => 22, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0,
            'lwop_days' => 0, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_employee_snapshots')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'employee_name' => 'TAX, CONTRIB',
            'monthly_salary' => $salary, 'salary_grade' => 15, 'salary_step' => 1, 'payroll_eligible' => 1, 'credit_eligible' => 1,
        ]);
        foreach (['pay_divisor' => ['working_days_per_month' => 22], 'tax_rules' => $taxRules] as $key => $value) {
            DB::table('hris.cutoff_config_snapshots')->insert([
                'cutoff_id' => $this->cutoffId, 'config_key' => $key, 'payload' => json_encode($value), 'payload_hash' => str_repeat('0', 64),
            ]);
        }
    }

    private function addComponent(string $code, string $type, bool $beforeTax, float $amount): void
    {
        DB::table('hris.cutoff_payroll_components')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'code' => $code, 'name' => $code,
            'type' => $type, 'taxable' => $beforeTax ? 1 : 0, 'amount' => $amount,
        ]);
    }

    private function computeTax(array $adjustments = []): float
    {
        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $this->cutoffId, 'title' => 'Contribution Tax Payroll', 'start_date' => '2027-08-01', 'end_date' => '2027-08-31',
            'cutoff_start_date' => '2027-08-01', 'cutoff_end_date' => '2027-08-31', 'payroll_type' => 'Regular',
            'tax_purpose' => $this->purpose, 'status' => 'draft', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId, 'employees' => [['userid' => $this->userId, 'adjustments' => $adjustments]],
        ])->assertOk();

        $rowId = DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->value('id');

        return (float) DB::table('hris.payroll_employee_components')
            ->where(['payroll_employee_id' => $rowId, 'code' => 'WITHHOLDING_TAX'])->value('amount');
    }

    /** @test */
    public function contributions_lower_withholding_tax_on_the_train_brackets()
    {
        // TRAIN 2023-onward annual table, as stored in payroll_tax_rules.
        $brackets = [];
        foreach ([[0, 250000, 0], [250000, 400000, 15], [400000, 800000, 20], [800000, 2000000, 25], [2000000, 8000000, 30], [8000000, null, 35]] as [$min, $max, $rate]) {
            $brackets[] = ['purpose' => $this->purpose, 'min_annual' => (string) $min, 'max_annual' => $max === null ? null : (string) $max, 'rate_percent' => (string) $rate, 'tax_mode' => 'progressive'];
        }
        $this->seedCutoff(42178, $brackets);
        $this->addComponent('GSIS_PS', 'deduction', true, 3796.02); // 9% of basic
        $this->addComponent('PHIC', 'deduction', true, 1054.45);    // 2.5% of basic
        $this->addComponent('HDMF', 'deduction', true, 200);

        // (42,178 - 5,050.47) x 12 = 445,530.36 -> 22,500 + 20% x 45,530.36 = 31,606.07 a year.
        $this->assertEqualsWithDelta(2633.84, $this->computeTax(), 0.01);
    }

    /** @test */
    public function a_cutoff_adjustment_to_a_before_tax_deduction_is_also_before_tax()
    {
        DB::table('hris.payroll_components')->update(['is_active' => 0]);
        DB::table('hris.payroll_components')->where('code', $this->testCode)->delete();
        DB::table('hris.payroll_components')->insert([
            'code' => $this->testCode, 'name' => 'Test Contribution', 'type' => 'deduction', 'is_global' => 0,
            'taxable' => 1, 'default_amount' => 0, 'is_active' => 1, 'is_cutoff_adjustable' => 1, 'sort_order' => 0,
        ]);

        $this->seedCutoff(22000, [['purpose' => $this->purpose, 'min_annual' => '0', 'max_annual' => null, 'rate_percent' => '10', 'tax_mode' => 'flat']]);

        // Contribution arrears of 1,000 come off before tax: 10% of 21,000.
        $this->assertEqualsWithDelta(2100, $this->computeTax([$this->testCode => 1000]), 0.01);
    }
}
