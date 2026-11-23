<?php

namespace Tests\Feature;

use App\Services\CutoffSnapshotService;
use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Employer shares (GSIS GS/EC) are recorded but never come out of the
 * employee's pay; Basic Pay Adjustments are taxed; Magna Carta benefits can be
 * limited to Permanent employees and reduced for days on leave, absent or LWOP
 * by a rule HR sets per component. Every write rolls back (InnoDB).
 */
class PayrollEmployerShareAndBenefitRulesTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9751;
    private int $cutoffId;
    private string $purpose = 'Payroll Monthly Salary';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId, 'fname' => 'Share', 'lname' => 'Rules', 'username' => 'sharerules' . $this->userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52,
        ]);

        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    protected function tearDown(): void
    {
        // tdh_user.users is MyISAM: not rolled back.
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function seedCutoff(float $salary = 22000): void
    {
        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Share Rules ' . uniqid(), 'status' => 'finalized', 'snapshot_version' => 1,
            'period_start' => '2027-09-01', 'period_end' => '2027-09-30', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $this->cutoffId, 'period_start' => '2027-09-01', 'period_end' => '2027-09-30',
            'total_working_days' => 22, 'days_present' => 22, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0,
            'lwop_days' => 0, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_employee_snapshots')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'employee_name' => 'RULES, SHARE',
            'monthly_salary' => $salary, 'salary_grade' => 15, 'salary_step' => 1, 'payroll_eligible' => 1, 'credit_eligible' => 1,
        ]);
        foreach (['pay_divisor' => ['working_days_per_month' => 22], 'tax_rules' => [['purpose' => $this->purpose, 'min_annual' => '0', 'max_annual' => null, 'rate_percent' => '10', 'tax_mode' => 'flat']]] as $key => $value) {
            DB::table('hris.cutoff_config_snapshots')->insert([
                'cutoff_id' => $this->cutoffId, 'config_key' => $key, 'payload' => json_encode($value), 'payload_hash' => str_repeat('0', 64),
            ]);
        }
    }

    private function addSnapshotComponent(string $code, string $type, bool $taxable, float $amount): void
    {
        DB::table('hris.cutoff_payroll_components')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'code' => $code, 'name' => $code,
            'type' => $type, 'taxable' => $taxable ? 1 : 0, 'amount' => $amount,
        ]);
    }

    private function compute(array $employee = []): object
    {
        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $this->cutoffId, 'title' => 'Share Rules Payroll', 'start_date' => '2027-09-01', 'end_date' => '2027-09-30',
            'cutoff_start_date' => '2027-09-01', 'cutoff_end_date' => '2027-09-30', 'payroll_type' => 'Regular',
            'tax_purpose' => $this->purpose, 'status' => 'draft', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.payroll_components')->update(['is_cutoff_adjustable' => 0]);

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId, 'employees' => [['userid' => $this->userId] + $employee],
        ])->assertOk();

        return DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->first();
    }

    private function line(int $payrollEmployeeId, string $code): ?object
    {
        return DB::table('hris.payroll_employee_components')
            ->where(['payroll_employee_id' => $payrollEmployeeId, 'code' => $code])->first();
    }

    /** @test */
    public function an_employer_share_is_recorded_but_does_not_reduce_net_pay()
    {
        $this->seedCutoff();
        $this->addSnapshotComponent('GSIS_PS', 'deduction', true, 1980);      // 9%
        $this->addSnapshotComponent('GSIS_GS', 'employer_share', false, 2640); // 12%

        $row = $this->compute();

        // Tax 10% of (22,000 - 1,980) = 2,002; net 22,000 - 1,980 - 2,002.
        $this->assertEqualsWithDelta(2002, (float) $this->line($row->id, 'WITHHOLDING_TAX')->amount, 0.01);
        $this->assertEqualsWithDelta(1980 + 2002, (float) $row->deductions, 0.01);
        $this->assertEqualsWithDelta(18018, (float) $row->net_pay, 0.01);
        $this->assertEqualsWithDelta(22000, (float) $row->gross_pay, 0.01);

        $share = $this->line($row->id, 'GSIS_GS');
        $this->assertNotNull($share, 'The employer share is kept with the payroll for remittance.');
        $this->assertSame('employer_share', $share->type);
        $this->assertEqualsWithDelta(2640, (float) $share->amount, 0.01);
    }

    /** @test */
    public function a_basic_pay_adjustment_is_taxed()
    {
        $this->seedCutoff();

        $row = $this->compute(['salary_adjustment' => 1000]);

        $this->assertEqualsWithDelta(23000, (float) $row->gross_pay, 0.01);
        $this->assertEqualsWithDelta(2300, (float) $this->line($row->id, 'WITHHOLDING_TAX')->amount, 0.01);
        $this->assertSame(1, (int) $this->line($row->id, 'SALARY_ADJUSTMENT')->taxable);
    }

    private function catalogComponent(string $code, array $values = []): void
    {
        DB::table('hris.payroll_components')->where('code', $code)->delete();
        DB::table('hris.payroll_components')->insert(array_merge([
            'code' => $code, 'name' => $code, 'type' => 'earning', 'is_global' => 1, 'taxable' => 0,
            'default_amount' => 500, 'is_active' => 1, 'is_cutoff_adjustable' => 0, 'sort_order' => 0,
        ], $values));
    }

    private function setEmployeeType(?string $type): void
    {
        DB::table('hris.employees')->where('user_id', $this->userId)->delete();
        DB::table('hris.employees')->insert(['user_id' => $this->userId, 'employee_type' => $type]);
    }

    private function dynamicCodes(): array
    {
        return app(PayrollServices::class)
            ->getEmployeeDynamicComponents($this->userId, '2027-09-01', '2027-09-30', 22000)
            ->pluck('code')->all();
    }

    /** @test */
    public function a_permanent_only_component_skips_employees_who_are_not_permanent()
    {
        DB::table('hris.payroll_components')->update(['is_active' => 0]);
        $this->catalogComponent('TEST_PERM_ONLY', ['permanent_only' => 1]);
        $this->catalogComponent('TEST_FOR_ALL');

        foreach (['Temporary', 'Job Order', null] as $type) {
            $this->setEmployeeType($type);
            $this->assertSame(['TEST_FOR_ALL'], $this->dynamicCodes(), 'Employee type ' . var_export($type, true));
        }

        $this->setEmployeeType('Permanent');
        $codes = $this->dynamicCodes();
        sort($codes);
        $this->assertSame(['TEST_FOR_ALL', 'TEST_PERM_ONLY'], $codes);
    }

    private function benefitLine(string $code, float $amount, string $rule, ?float $rate = null, bool $taxable = false, string $type = 'earning'): object
    {
        return (object) [
            'payroll_component_id' => 1, 'code' => $code, 'name' => ucfirst(strtolower($code)), 'type' => $type,
            'taxable' => $taxable, 'amount' => $amount, 'absence_reduction' => $rule, 'absence_reduction_rate' => $rate,
        ];
    }

    /** @test */
    public function reductions_follow_each_components_rule()
    {
        $reductions = app(PayrollServices::class)->absenceReductions(collect([
            $this->benefitLine('SUBSISTENCE', 1500, 'per_day', 50),
            $this->benefitLine('HAZARD', 5500, 'prorate', null, true),
            $this->benefitLine('LAUNDRY', 150, 'none'),
            $this->benefitLine('GSIS_PS', 1980, 'per_day', 50, true, 'deduction'), // deductions are never reduced
        ]), 3, 22)->keyBy('code');

        $this->assertSame(['SUBSISTENCE_LEAVE_REDUCTION', 'HAZARD_LEAVE_REDUCTION'], $reductions->keys()->all());

        $subsistence = $reductions['SUBSISTENCE_LEAVE_REDUCTION'];
        $this->assertSame('earning', $subsistence->type);
        $this->assertEqualsWithDelta(-150, $subsistence->amount, 0.001);       // 3 days x 50
        $this->assertFalse($subsistence->taxable);

        $hazard = $reductions['HAZARD_LEAVE_REDUCTION'];
        $this->assertEqualsWithDelta(-750, $hazard->amount, 0.001);            // 5,500 / 22 x 3
        $this->assertTrue($hazard->taxable, 'A reduction has the same tax treatment as its component.');
        $this->assertStringContainsString('3', $hazard->computation_basis);
    }

    /** @test */
    public function a_reduction_never_exceeds_the_allowance_and_no_days_off_means_none()
    {
        $service = app(PayrollServices::class);

        $capped = $service->absenceReductions(collect([$this->benefitLine('SUBSISTENCE', 1500, 'per_day', 50)]), 40, 22);
        $this->assertEqualsWithDelta(-1500, $capped->first()->amount, 0.001);

        $this->assertTrue($service->absenceReductions(collect([$this->benefitLine('SUBSISTENCE', 1500, 'per_day', 50)]), 0, 22)->isEmpty());
    }

    /** @test */
    public function the_snapshot_stores_the_reduction_beside_its_allowance()
    {
        DB::table('hris.payroll_components')->update(['is_active' => 0]);
        $this->catalogComponent('TEST_SUBSIST', ['default_amount' => 1500, 'absence_reduction' => 'per_day', 'absence_reduction_rate' => 50]);
        $this->setEmployeeType('Permanent');
        $this->seedCutoff();

        $method = new \ReflectionMethod(CutoffSnapshotService::class, 'insertPayComponents');
        $method->setAccessible(true);
        // Half a day absent, 1 day LWOP, 1.5 days paid leave = 3 days off.
        $method->invoke(app(CutoffSnapshotService::class), $this->cutoffId, $this->userId, ['amount' => 22000], '2027-09-01', '2027-09-30', 3.0);

        $rows = DB::table('hris.cutoff_payroll_components')->where(['cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId])
            ->get()->keyBy('code');
        $this->assertEqualsWithDelta(1500, (float) $rows['TEST_SUBSIST']->amount, 0.01);
        $this->assertEqualsWithDelta(-150, (float) $rows['TEST_SUBSIST_LEAVE_REDUCTION']->amount, 0.01);
        $this->assertNotEmpty($rows['TEST_SUBSIST_LEAVE_REDUCTION']->computation_basis);
    }

    /** @test */
    public function the_catalog_saves_the_new_type_and_rules()
    {
        $this->withoutMiddleware()->postJson('/payroll-components', ['components' => [
            ['code' => 'TEST_EMPLOYER', 'name' => 'Test Employer Share', 'type' => 'employer_share', 'absence_reduction' => 'per_day', 'absence_reduction_rate' => 50],
            ['code' => 'TEST_BENEFIT', 'name' => 'Test Benefit', 'type' => 'earning', 'permanent_only' => true, 'absence_reduction' => 'per_day', 'absence_reduction_rate' => 50],
        ]])->assertOk();

        $employer = DB::table('hris.payroll_components')->where('code', 'TEST_EMPLOYER')->first();
        $this->assertSame('employer_share', $employer->type);
        $this->assertSame('none', $employer->absence_reduction, 'Only earnings are reduced for days off.');

        $benefit = DB::table('hris.payroll_components')->where('code', 'TEST_BENEFIT')->first();
        $this->assertSame(1, (int) $benefit->permanent_only);
        $this->assertSame('per_day', $benefit->absence_reduction);
        $this->assertEqualsWithDelta(50, (float) $benefit->absence_reduction_rate, 0.001);

        // Saving an employee's settings writes the catalog row back: the type must survive.
        $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->userId, 'salary_grade' => 15, 'step' => 1, 'basic_salary' => 22000,
            'components' => [['code' => 'TEST_EMPLOYER', 'name' => 'Test Employer Share', 'type' => 'employer_share', 'amount_override' => 2640]],
        ])->assertOk();
        $this->assertSame('employer_share', DB::table('hris.payroll_components')->where('code', 'TEST_EMPLOYER')->value('type'));
    }

    /** @test */
    public function a_per_day_rule_needs_a_rate()
    {
        $this->withoutMiddleware()->postJson('/payroll-components', ['components' => [
            ['code' => 'TEST_NO_RATE', 'name' => 'No Rate', 'type' => 'earning', 'absence_reduction' => 'per_day'],
        ]])->assertStatus(422);
    }
}
