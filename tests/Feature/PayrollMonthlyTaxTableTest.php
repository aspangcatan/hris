<?php

namespace Tests\Feature;

use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tax rules carry a basis: 'annual' brackets are applied to monthly taxable x 12
 * (then / 12); 'monthly' brackets - the BIR monthly withholding table, RR 11-2018
 * Annex E as amended, 2023 onward - are applied to the monthly taxable as-is.
 * The values live in payroll_tax_rules, editable on the Taxation page.
 */
class PayrollMonthlyTaxTableTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTHLY = 'BIR Monthly Withholding Tax';

    private function brackets(string $basis, array $rows): \Illuminate\Support\Collection
    {
        return collect($rows)->map(fn($r) => (object) [
            'purpose' => 'P', 'min_annual' => (string) $r[0], 'max_annual' => $r[1] === null ? null : (string) $r[1],
            'rate_percent' => (string) $r[2], 'tax_mode' => 'progressive', 'basis' => $basis,
        ]);
    }

    private function monthlyTable(): \Illuminate\Support\Collection
    {
        return $this->brackets('monthly', [[0, 20833, 0], [20833, 33333, 15], [33333, 66667, 20], [66667, 166667, 25], [166667, 666667, 30], [666667, null, 35]]);
    }

    public function monthlyCases(): array
    {
        return [
            'at the exemption ceiling' => [20833, 0],
            '15% bracket' => [25000, 625.05],              // 15% x 4,167
            'top of 15% bracket' => [33333, 1875],
            '20% bracket (Oct 2026 example)' => [46713.44, 4551.09], // 1,875 + 20% x 13,380.44
            '25% bracket' => [100000, 16875.05],           // 8,541.80 + 25% x 33,333
            '30% bracket' => [200000, 43541.70],           // 33,541.80 + 30% x 33,333
            '35% bracket' => [700000, 195208.35],          // 183,541.80 + 35% x 33,333
        ];
    }

    /**
     * @test
     * @dataProvider monthlyCases
     */
    public function monthly_brackets_reproduce_the_bir_monthly_table(float $taxable, float $expected)
    {
        $tax = app(PayrollServices::class)->withholdingTax($taxable, 'P', $this->monthlyTable());

        $this->assertEqualsWithDelta($expected, $tax, 0.005);
    }

    /** @test */
    public function annual_brackets_still_annualize()
    {
        $annual = $this->brackets('annual', [[0, 250000, 0], [250000, 400000, 15], [400000, 800000, 20], [800000, 2000000, 25], [2000000, 8000000, 30], [8000000, null, 35]]);

        // (46,713.44 x 12 = 560,561.28) -> 22,500 + 20% x 160,561.28 = 54,612.26 a year.
        $this->assertEqualsWithDelta(4551.02, app(PayrollServices::class)->withholdingTax(46713.44, 'P', $annual), 0.005);

        // Rules saved before the basis existed are annual.
        $legacy = $annual->map(function ($r) {
            unset($r->basis);
            return $r;
        });
        $this->assertEqualsWithDelta(4551.02, app(PayrollServices::class)->withholdingTax(46713.44, 'P', $legacy), 0.005);
    }

    /** @test */
    public function the_bir_monthly_table_is_stored_in_the_database()
    {
        $rows = DB::table('hris.payroll_tax_rules')->where('purpose', self::MONTHLY)->where('is_active', 1)
            ->orderBy('min_annual')->get();

        $this->assertSame(
            [[0.0, 20833.0, 0.0], [20833.0, 33333.0, 15.0], [33333.0, 66667.0, 20.0], [66667.0, 166667.0, 25.0], [166667.0, 666667.0, 30.0], [666667.0, null, 35.0]],
            $rows->map(fn($r) => [(float) $r->min_annual, $r->max_annual === null ? null : (float) $r->max_annual, (float) $r->rate_percent])->all()
        );
        $this->assertSame(['monthly'], $rows->pluck('basis')->unique()->values()->all());
        $this->assertSame(['progressive'], $rows->pluck('tax_mode')->unique()->values()->all());

        // Read from the database, not code: the live table gives the BIR figure.
        $this->assertEqualsWithDelta(4551.09, app(PayrollServices::class)->withholdingTax(46713.44, self::MONTHLY), 0.005);
    }

    /** @test */
    public function a_purpose_cannot_mix_annual_and_monthly_rules()
    {
        $error = app(PayrollServices::class)->validateTaxRuleSet('P', [
            ['min_annual' => 0, 'max_annual' => 20833, 'tax_mode' => 'progressive', 'basis' => 'monthly'],
            ['min_annual' => 20833, 'max_annual' => null, 'tax_mode' => 'progressive', 'basis' => 'annual'],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('basis', $error);
    }

    /** @test */
    public function the_taxation_page_saves_the_basis()
    {
        Auth::shouldReceive('id')->andReturn(1);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        $this->withoutMiddleware()->postJson('/payroll-tax-rules', ['rules' => [
            ['purpose' => 'TEST MONTHLY BASIS', 'min_annual' => 0, 'max_annual' => null, 'rate_percent' => 10, 'tax_mode' => 'flat', 'basis' => 'monthly'],
        ]])->assertOk();

        $this->assertSame('monthly', DB::table('hris.payroll_tax_rules')->where('purpose', 'TEST MONTHLY BASIS')->value('basis'));
    }

    /** @test */
    public function payroll_uses_the_basis_saved_with_the_cutoff()
    {
        $userId = 9771;
        DB::table('tdh_user.users')->where('id', $userId)->delete();
        DB::table('tdh_user.users')->insert(['id' => $userId, 'fname' => 'Monthly', 'lname' => 'Table', 'username' => 'monthlytable' . $userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52]);
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        try {
            $cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
                'title' => 'Monthly Table ' . uniqid(), 'status' => 'finalized', 'snapshot_version' => 1,
                'period_start' => '2027-10-01', 'period_end' => '2027-10-31', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('hris.cutoff_summaries')->insert(['employee_id' => $userId, 'cutoff_id' => $cutoffId, 'period_start' => '2027-10-01',
                'period_end' => '2027-10-31', 'total_working_days' => 22, 'days_present' => 22, 'days_absent' => 0, 'late_minutes' => 0,
                'undertime_minutes' => 0, 'lwop_days' => 0, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('hris.cutoff_employee_snapshots')->insert(['cutoff_id' => $cutoffId, 'employee_id' => $userId, 'employee_name' => 'TABLE, MONTHLY',
                'monthly_salary' => 46713.44, 'salary_grade' => 15, 'salary_step' => 1, 'payroll_eligible' => 1, 'credit_eligible' => 1]);
            $rules = $this->monthlyTable()->map(fn($r) => ['purpose' => self::MONTHLY] + (array) $r)->all();
            foreach (['pay_divisor' => ['working_days_per_month' => 22], 'tax_rules' => $rules] as $key => $value) {
                DB::table('hris.cutoff_config_snapshots')->insert(['cutoff_id' => $cutoffId, 'config_key' => $key,
                    'payload' => json_encode($value), 'payload_hash' => str_repeat('0', 64)]);
            }
            $payrollId = DB::table('hris.payrolls')->insertGetId(['cutoff_id' => $cutoffId, 'title' => 'Monthly Table Payroll',
                'start_date' => '2027-10-01', 'end_date' => '2027-10-31', 'cutoff_start_date' => '2027-10-01', 'cutoff_end_date' => '2027-10-31',
                'payroll_type' => 'Regular', 'tax_purpose' => self::MONTHLY, 'status' => 'draft', 'prepared_by' => $userId,
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('hris.payroll_components')->update(['is_cutoff_adjustable' => 0]);

            $this->withoutMiddleware()->postJson('/save_payroll_employee', ['payroll_id' => $payrollId, 'employees' => [['userid' => $userId]]])->assertOk();

            $rowId = DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->value('id');
            $tax = (float) DB::table('hris.payroll_employee_components')->where(['payroll_employee_id' => $rowId, 'code' => 'WITHHOLDING_TAX'])->value('amount');
            $this->assertEqualsWithDelta(4551.09, $tax, 0.005);
        } finally {
            DB::table('tdh_user.users')->where('id', $userId)->delete(); // MyISAM
        }
    }
}
