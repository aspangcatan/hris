<?php

namespace Tests\Feature;

use App\Services\CutoffSnapshotService;
use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payroll reading from the cutoff snapshot, net pay, the two pay halves, and
 * deductions taken before tax. Every write rolls back.
 */
class PayrollSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9731;
    private int $cutoffId;
    private string $purpose = 'Payroll Monthly Salary';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId, 'fname' => 'Snap', 'lname' => 'Payroll', 'username' => 'snappayroll' . $this->userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52,
        ]);

        // A clean slate: only what each test adds counts.
        DB::table('hris.payroll_components')->update(['is_active' => 0]);
        DB::table('hris.payroll_tax_rules')->update(['is_active' => 0]);
        DB::table('hris.payroll_tax_rules')->insert([
            'purpose' => $this->purpose, 'min_annual' => 0, 'max_annual' => null, 'rate_percent' => 10,
            'tax_mode' => 'flat', 'is_active' => 1, 'sort_order' => 1,
        ]);

        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function seedCutoff(array $employee = [], array $summary = [], float $taxRate = 10): int
    {
        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Payroll Snapshot ' . uniqid(), 'status' => 'finalized', 'snapshot_version' => 1,
            'period_start' => '2027-07-01', 'period_end' => '2027-07-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert(array_merge([
            'employee_id' => $this->userId, 'cutoff_id' => $this->cutoffId, 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'total_working_days' => 22, 'days_present' => 22, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0,
            'lwop_days' => 0, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now(),
        ], $summary));
        DB::table('hris.cutoff_employee_snapshots')->insert(array_merge([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'employee_name' => 'PAYROLL, SNAP',
            'monthly_salary' => 22000, 'salary_grade' => 15, 'salary_step' => 3, 'position' => 'Saved Position',
            'division_id' => 7, 'section_id' => 9, 'payroll_eligible' => 1, 'credit_eligible' => 1,
        ], $employee));
        foreach ([
            'pay_divisor' => ['working_days_per_month' => 22],
            'tax_rules' => [['purpose' => $this->purpose, 'min_annual' => '0', 'max_annual' => null, 'rate_percent' => (string) $taxRate, 'tax_mode' => 'flat']],
        ] as $key => $value) {
            DB::table('hris.cutoff_config_snapshots')->insert([
                'cutoff_id' => $this->cutoffId, 'config_key' => $key, 'payload' => json_encode($value), 'payload_hash' => str_repeat('0', 64),
            ]);
        }

        return $this->cutoffId;
    }

    private function addComponent(string $code, string $type, bool $taxable, float $amount): void
    {
        DB::table('hris.cutoff_payroll_components')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'code' => $code, 'name' => $code,
            'type' => $type, 'taxable' => $taxable ? 1 : 0, 'amount' => $amount,
        ]);
    }

    private function compute()
    {
        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $this->cutoffId, 'title' => 'Snapshot Payroll', 'start_date' => '2027-07-01', 'end_date' => '2027-07-31',
            'cutoff_start_date' => '2027-07-01', 'cutoff_end_date' => '2027-07-31', 'payroll_type' => 'Regular',
            'status' => 'draft', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$payrollId, $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId, 'employees' => [['userid' => $this->userId]],
        ])];
    }

    private function result(int $payrollId): object
    {
        return DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->first();
    }

    private function tax(int $payrollEmployeeId): float
    {
        return (float) DB::table('hris.payroll_employee_components')
            ->where(['payroll_employee_id' => $payrollEmployeeId, 'code' => 'WITHHOLDING_TAX'])->value('amount');
    }

    /** @test */
    public function a_new_payroll_needs_a_whole_month_cutoff()
    {
        $this->seedCutoff();
        $create = fn() => $this->withoutMiddleware()->postJson('/save_payroll', ['title' => 'Month check', 'cutoff_id' => $this->cutoffId]);

        $create()->assertOk();

        foreach ([['2027-07-01', '2027-07-15'], ['2027-07-16', '2027-07-31'], ['2027-07-02', '2027-07-31']] as [$start, $end]) {
            DB::table('hris.cutoff_summaries_master')->where('id', $this->cutoffId)->update(['period_start' => $start, 'period_end' => $end]);
            $create()->assertStatus(422)->assertSee('whole-month cutoff', false);
        }

        // February ends on its own last day.
        DB::table('hris.cutoff_summaries_master')->where('id', $this->cutoffId)->update(['period_start' => '2027-02-01', 'period_end' => '2027-02-28']);
        $create()->assertOk();
    }

    /** @test */
    public function non_taxable_earnings_reach_net_pay_but_are_not_taxed()
    {
        $this->seedCutoff();
        $this->addComponent('PERA', 'earning', false, 2000);
        $this->addComponent('LONGEVITY', 'earning', true, 1000);

        [$id, $response] = $this->compute();
        $response->assertOk();
        $row = $this->result($id);

        $this->assertEqualsWithDelta(25000, (float) $row->gross_pay, 0.01);
        $this->assertEqualsWithDelta(2300, $this->tax($row->id), 0.01); // 10% of 23,000
        $this->assertEqualsWithDelta(22700, (float) $row->net_pay, 0.01);
    }

    /** @test */
    public function the_two_halves_add_up_to_net_pay_exactly()
    {
        $this->seedCutoff(['monthly_salary' => 22000.01]);
        DB::table('hris.cutoff_config_snapshots')->where(['cutoff_id' => $this->cutoffId, 'config_key' => 'tax_rules'])->update(['payload' => '[]']);

        [$id, $response] = $this->compute();
        $response->assertOk();
        $row = $this->result($id);

        $this->assertEquals(22000.01, (float) $row->net_pay);
        $this->assertEquals(11000.01, (float) $row->first_half_10th);
        $this->assertEquals(11000.00, (float) $row->second_half_25th);
    }

    /** @test */
    public function a_deduction_marked_before_tax_lowers_the_tax_and_others_do_not()
    {
        $this->seedCutoff();
        $this->addComponent('GSIS_PS', 'deduction', true, 2000);
        $this->addComponent('COOP_LOAN', 'deduction', false, 500);

        [$id, $response] = $this->compute();
        $response->assertOk();
        $row = $this->result($id);

        $this->assertEqualsWithDelta(2000, $this->tax($row->id), 0.01); // 10% of (22,000 - 2,000)
        $this->assertEqualsWithDelta(22000 - 2000 - 500 - 2000, (float) $row->net_pay, 0.01);
    }

    /** @test */
    public function pay_comes_from_the_snapshot_not_the_live_settings()
    {
        $this->seedCutoff();
        $this->addComponent('PERA', 'earning', false, 2000);
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 20, 'step' => 1, 'basic_salary' => 90000]);
        DB::table('hris.payroll_tax_rules')->where('purpose', $this->purpose)->where('is_active', 1)->update(['rate_percent' => 50]);

        [$id, $response] = $this->compute();
        $response->assertOk();
        $row = $this->result($id);

        $this->assertEquals(22000, (float) $row->basic_salary);
        $this->assertEquals(2000, (float) $row->other_earnings);
        $this->assertEqualsWithDelta(2200, $this->tax($row->id), 0.01);
    }

    /** @test */
    public function lwop_absences_and_minutes_are_charged_at_the_saved_rate()
    {
        $this->seedCutoff([], ['days_absent' => 1, 'lwop_days' => 1.5, 'late_minutes' => 30, 'undertime_minutes' => 15, 'approved_pass_slips_minutes' => 15]);

        [$id, $response] = $this->compute();
        $response->assertOk();

        // 22,000 / 22 = 1,000 a day: 2.5 days + 60 minutes at 1,000 / 480.
        $this->assertEqualsWithDelta(2500 + 125, (float) $this->result($id)->late_absent, 0.01);
    }

    /** @test */
    public function the_real_office_is_stored_from_the_snapshot()
    {
        $this->seedCutoff();

        [$id, $response] = $this->compute();
        $response->assertOk();
        $row = $this->result($id);

        $this->assertSame(7, (int) $row->division);
        $this->assertSame(9, (int) $row->section);
    }

    /** @test */
    public function the_payroll_list_shows_the_saved_position_and_grade()
    {
        $this->seedCutoff();
        [$id, $response] = $this->compute();
        $response->assertOk();

        DB::table('tdh_user.users')->where('id', $this->userId)->update(['designation' => 53]);
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 20, 'step' => 8, 'basic_salary' => 90000]);

        $row = app(PayrollServices::class)->getPayrollEmployee($id)->first();
        $this->assertSame('Saved Position', $row->designation);
        $this->assertEquals(15, $row->salary_grade);
        $this->assertEquals(3, $row->step);
    }

    /** @test */
    public function an_employee_without_salary_cannot_be_computed_and_is_named()
    {
        $this->seedCutoff(['monthly_salary' => null, 'payroll_eligible' => 0, 'exclusion_reason' => 'No salary on file']);

        [, $response] = $this->compute();

        $response->assertStatus(422);
        $this->assertStringContainsString('PAYROLL, SNAP', $response->json('error'));
    }

    /** @test */
    public function an_employee_without_salary_is_left_off_the_payroll_list()
    {
        $this->seedCutoff(['monthly_salary' => null, 'payroll_eligible' => 0]);
        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $this->cutoffId, 'title' => 'Snapshot Payroll', 'start_date' => '2027-07-01', 'end_date' => '2027-07-31',
            'status' => 'draft', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withoutMiddleware()->getJson("/payroll/{$payrollId}/cutoff-employees")->assertOk()->assertJsonCount(0, 'data');
    }

    /** @test */
    public function a_cutoff_without_a_snapshot_still_reads_the_live_settings()
    {
        $id = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Legacy ' . uniqid(), 'status' => 'finalized', 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->cutoffId = $id;
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $id, 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'total_working_days' => 22, 'days_present' => 22, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0,
            'lwop_days' => 0, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 33000]);

        [$payrollId, $response] = $this->compute();
        $response->assertOk();
        $row = $this->result($payrollId);

        $this->assertEquals(33000, (float) $row->basic_salary);
        $this->assertSame(2, (int) $row->division);
    }

    /** @test */
    public function an_expired_employee_component_is_not_captured()
    {
        $componentId = DB::table('hris.payroll_components')->insertGetId([
            'code' => 'TEST_EXPIRED', 'name' => 'Expired', 'type' => 'deduction', 'is_global' => 0, 'taxable' => 0,
            'default_amount' => 0, 'calculation_type' => 'fixed', 'is_active' => 1, 'is_cutoff_adjustable' => 0, 'sort_order' => 1,
        ]);
        DB::table('hris.employee_payroll_components')->insert([
            'payroll_component_id' => $componentId, 'userid' => $this->userId, 'amount' => 500, 'is_active' => 1,
            'is_excluded' => 0, 'effective_from' => '2027-01-01', 'effective_to' => '2027-03-31',
        ]);

        $service = app(PayrollServices::class);
        $this->assertCount(0, $service->getEmployeeDynamicComponents($this->userId, '2027-07-01', '2027-07-31', 22000));
        $this->assertCount(1, $service->getEmployeeDynamicComponents($this->userId, '2027-02-01', '2027-02-28', 22000));
    }

    private function addDay(string $date, int $requiredMinutes, bool $absent = true, float $lwopFraction = 0): void
    {
        DB::table('hris.cutoff_summary_days')->insert([
            'cutoff_id' => $this->cutoffId, 'employee_id' => $this->userId, 'date' => $date, 'late_minutes' => 0, 'undertime_minutes' => 0,
            'is_absent' => $absent ? 1 : 0, 'is_holiday' => 0, 'required_minutes' => $requiredMinutes, 'lwop_fraction' => $lwopFraction,
        ]);
    }

    /** @test */
    public function the_hourly_and_minute_rates_are_the_same_formula_for_every_schedule()
    {
        $rates = app(PayrollServices::class)->payRates(22000);

        $this->assertEqualsWithDelta(1000, $rates['daily'], 0.0001);
        $this->assertEqualsWithDelta(125, $rates['hourly'], 0.0001);
        $this->assertEqualsWithDelta(125 / 60, $rates['minute'], 0.0001);
    }

    /** @test */
    public function an_absent_ten_hour_day_costs_ten_hours()
    {
        $this->seedCutoff([], ['days_absent' => 1]);
        $this->addDay('2027-07-05', 600);

        [$id, $response] = $this->compute();
        $response->assertOk();

        $this->assertEqualsWithDelta(1250, (float) $this->result($id)->late_absent, 0.01);
    }

    /** @test */
    public function an_absent_eight_hour_day_is_unchanged()
    {
        $this->seedCutoff([], ['days_absent' => 1]);
        $this->addDay('2027-07-05', 480);

        [$id, $response] = $this->compute();
        $response->assertOk();

        $this->assertEqualsWithDelta(1000, (float) $this->result($id)->late_absent, 0.01);
    }

    /** @test */
    public function the_same_four_hours_of_lwop_cost_the_same_on_either_shift()
    {
        $this->seedCutoff([], ['lwop_days' => 0.4]);
        $this->addDay('2027-07-05', 600, false, 0.4);

        [$tenHour, $response] = $this->compute();
        $response->assertOk();
        $this->assertEqualsWithDelta(500, (float) $this->result($tenHour)->late_absent, 0.01);

        $this->seedCutoff([], ['lwop_days' => 0.5]);
        $this->addDay('2027-07-05', 480, false, 0.5);

        [$eightHour, $response] = $this->compute();
        $response->assertOk();
        $this->assertEqualsWithDelta(500, (float) $this->result($eightHour)->late_absent, 0.01);
    }

    /** @test */
    public function a_month_mixing_shifts_charges_each_day_by_its_own_hours()
    {
        $this->seedCutoff([], ['days_absent' => 2]);
        $this->addDay('2027-07-05', 480);
        $this->addDay('2027-07-06', 600);

        [$id, $response] = $this->compute();
        $response->assertOk();

        $this->assertEqualsWithDelta(2250, (float) $this->result($id)->late_absent, 0.01);
    }

    /** @test */
    public function the_rates_saved_with_the_cutoff_are_the_ones_used()
    {
        $this->seedCutoff(['daily_base_rate' => 1600, 'hourly_rate' => 200, 'minute_rate' => 200 / 60], ['late_minutes' => 60]);

        [$id, $response] = $this->compute();
        $response->assertOk();

        $this->assertEqualsWithDelta(200, (float) $this->result($id)->late_absent, 0.01);
    }

    /** @test */
    public function generating_a_compressed_schedule_saves_the_rates_and_charges_by_it()
    {
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 22000]);
        $shift = fn($code, $in, $out) => DB::table('hris.schedule_master_lists')->insertGetId([
            'label' => $code, 'code' => $code, 'type' => 'FIXED', 'time_in' => $in, 'time_in_latest' => $in, 'time_out' => $out,
            'time_out_latest' => $out, 'break_out' => '12:00:00', 'break_in' => '13:00:00',
        ]);
        $cww = $shift('TSTCWW', '07:00:00', '18:00:00');
        $off = (int) DB::table('hris.schedule_master_lists')->where('code', 'OFF')->value('id');

        $workDays = 0;
        for ($day = \Carbon\Carbon::parse('2027-07-01'); $day->lte('2027-07-31'); $day->addDay()) {
            $isWork = $day->dayOfWeekIso <= 4;
            $workDays += $isWork ? 1 : 0;
            DB::table('hris.schedules')->insert([
                'employee_id' => $this->userId, 'schedule_id' => $isWork ? $cww : $off, 'schedule_date' => $day->toDateString(),
                'updated_by' => $this->userId, 'created_at' => now(),
            ]);
        }

        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Compressed ' . uniqid(), 'status' => 'draft', 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $this->cutoffId, 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'total_working_days' => 22, 'days_present' => 0, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CutoffSnapshotService::class)->generate($this->cutoffId, $this->userId);
        DB::table('hris.cutoff_summaries_master')->where('id', $this->cutoffId)->update(['status' => 'finalized']);

        $saved = DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $this->cutoffId)->first();
        $this->assertEqualsWithDelta(1000, (float) $saved->daily_base_rate, 0.0001);
        $this->assertEqualsWithDelta(125, (float) $saved->hourly_rate, 0.0001);
        $this->assertEqualsWithDelta(125 / 60, (float) $saved->minute_rate, 0.0001);
        $this->assertEquals(10, $saved->scheduled_hours_per_day);
        $this->assertEqualsWithDelta(1250, (float) $saved->scheduled_daily_rate, 0.0001);

        $days = DB::table('hris.cutoff_summary_days')->where('cutoff_id', $this->cutoffId)->get()->keyBy('date');
        $this->assertEqualsWithDelta(1250, (float) $days['2027-07-05']->scheduled_daily_rate, 0.0001); // Monday
        $this->assertEqualsWithDelta(0, (float) $days['2027-07-09']->scheduled_daily_rate, 0.0001);    // Friday, OFF

        $summary = DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->cutoffId)->first();
        $this->assertSame($workDays, (int) $summary->days_absent);
        $this->assertSame($workDays * 600, (int) $summary->scheduled_absent_minutes);
        $this->assertEqualsWithDelta($workDays * 1250, (float) $summary->payroll_absence_deduction_basis, 0.01);

        // Changing the schedule later does not change what is charged.
        DB::table('hris.schedules')->where('employee_id', $this->userId)->update(['schedule_id' => $off]);
        [$id, $response] = $this->compute();
        $response->assertOk();
        $this->assertEqualsWithDelta($workDays * 1250, (float) $this->result($id)->late_absent, 0.01);
    }

    private function shift(string $code, string $in, string $out, ?int $computationId): int
    {
        return DB::table('hris.schedule_master_lists')->insertGetId([
            'label' => $code, 'code' => $code, 'type' => 'FIXED', 'time_in' => $in, 'time_in_latest' => $in, 'time_out' => $out,
            'time_out_latest' => $out, 'break_out' => '12:00:00', 'break_in' => '13:00:00', 'pay_computation_id' => $computationId,
        ]);
    }

    private function draftJuly(): void
    {
        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Computation ' . uniqid(), 'status' => 'draft', 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $this->cutoffId, 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'total_working_days' => 22, 'days_present' => 0, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @test */
    public function a_shift_uses_the_computation_set_on_it()
    {
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 26000]);
        $computation = DB::table('hris.pay_computations')->insertGetId([
            'name' => 'Test beyond 40 ' . uniqid(), 'working_days_per_month' => 26, 'standard_hours_per_day' => 10, 'is_default' => 0,
        ]);
        $long = $this->shift('TSTLONG', '07:00:00', '18:00:00', $computation);
        $off = (int) DB::table('hris.schedule_master_lists')->where('code', 'OFF')->value('id');

        for ($day = \Carbon\Carbon::parse('2027-07-01'); $day->lte('2027-07-31'); $day->addDay()) {
            DB::table('hris.schedules')->insert([
                'employee_id' => $this->userId, 'schedule_id' => $day->toDateString() === '2027-07-05' ? $long : $off,
                'schedule_date' => $day->toDateString(), 'updated_by' => $this->userId, 'created_at' => now(),
            ]);
        }
        $this->draftJuly();
        app(CutoffSnapshotService::class)->generate($this->cutoffId, $this->userId);
        DB::table('hris.cutoff_summaries_master')->where('id', $this->cutoffId)->update(['status' => 'finalized']);

        // 26,000 / 26 = 1,000 a day; / 10 = 100 an hour; a 10-hour day = 1,000.
        $saved = DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $this->cutoffId)->first();
        $this->assertEqualsWithDelta(100, (float) $saved->hourly_rate, 0.0001);
        $this->assertSame($computation, (int) $saved->pay_computation_id);
        $this->assertStringContainsString('Test beyond 40', $saved->pay_computation_name);

        // Editing the computation afterwards does not change what is charged.
        DB::table('hris.pay_computations')->where('id', $computation)->update(['working_days_per_month' => 10]);
        [$id, $response] = $this->compute();
        $response->assertOk();
        $this->assertEqualsWithDelta(1000, (float) $this->result($id)->late_absent, 0.01);
    }

    /** @test */
    public function an_unassigned_shift_uses_the_default_computation()
    {
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 22000]);
        $this->draftJuly();
        app(CutoffSnapshotService::class)->generate($this->cutoffId, $this->userId);

        $saved = DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $this->cutoffId)->first();
        $this->assertSame('40 hours per week computation', $saved->pay_computation_name);
        $this->assertEqualsWithDelta(125, (float) $saved->hourly_rate, 0.0001);
    }

    /** @test */
    public function changing_the_default_divisors_changes_the_rates()
    {
        DB::table('hris.pay_computations')->where('is_default', 1)->update(['working_days_per_month' => 20, 'standard_hours_per_day' => 10]);

        $rates = app(PayrollServices::class)->payRates(20000);

        $this->assertEqualsWithDelta(1000, $rates['daily'], 0.0001);
        $this->assertEqualsWithDelta(100, $rates['hourly'], 0.0001);
    }

    /** @test */
    public function the_settings_page_saves_a_computation_and_assigns_a_shift()
    {
        $this->withoutMiddleware();
        $name = 'Test setting ' . uniqid();
        $this->postJson('/pay-computations', ['name' => $name, 'working_days_per_month' => 26, 'standard_hours_per_day' => 8])->assertOk();
        $id = (int) DB::table('hris.pay_computations')->where('name', $name)->value('id');

        // Editing by id, and names stay unique.
        $this->postJson('/pay-computations', ['id' => $id, 'name' => $name, 'working_days_per_month' => 25, 'standard_hours_per_day' => 8])->assertOk();
        $this->assertEquals(25, DB::table('hris.pay_computations')->where('id', $id)->value('working_days_per_month'));
        $this->postJson('/pay-computations', ['name' => $name, 'working_days_per_month' => 22, 'standard_hours_per_day' => 8])->assertStatus(422);

        $shift = $this->shift('TSTSET', '08:00:00', '17:00:00', null);
        $this->postJson('/pay-computations/assign-shift', ['shift_id' => $shift, 'pay_computation_id' => $id])->assertOk();
        $this->assertSame($id, (int) DB::table('hris.schedule_master_lists')->where('id', $shift)->value('pay_computation_id'));

        $list = $this->getJson('/pay-computations/list')->assertOk();
        $this->assertSame(8.0, (float) collect($list->json('shifts'))->firstWhere('id', $shift)['hours']);

        // In use: can't be deleted until the shift moves.
        $this->deleteJson("/pay-computations/{$id}")->assertStatus(422);
        $this->postJson('/pay-computations/assign-shift', ['shift_id' => $shift, 'pay_computation_id' => null])->assertOk();
        $this->deleteJson("/pay-computations/{$id}")->assertOk();
    }

    /** @test */
    public function there_is_always_exactly_one_default()
    {
        $this->withoutMiddleware();
        $default = DB::table('hris.pay_computations')->where('is_default', 1)->first();

        $this->postJson('/pay-computations', ['id' => $default->id, 'name' => $default->name, 'working_days_per_month' => 22, 'standard_hours_per_day' => 8, 'is_default' => false])->assertStatus(422);
        $this->deleteJson("/pay-computations/{$default->id}")->assertStatus(422);

        $name = 'Test new default ' . uniqid();
        $this->postJson('/pay-computations', ['name' => $name, 'working_days_per_month' => 22, 'standard_hours_per_day' => 8, 'is_default' => true])->assertOk();
        $this->assertSame(1, DB::table('hris.pay_computations')->where('is_default', 1)->count());
        $this->assertSame($name, DB::table('hris.pay_computations')->where('is_default', 1)->value('name'));
    }

    /** @test */
    public function charges_stop_at_the_monthly_basic()
    {
        $this->seedCutoff([], ['days_absent' => 23]);

        [$id, $response] = $this->compute();
        $response->assertOk();

        $this->assertEquals(22000, (float) $this->result($id)->late_absent);
    }

    /** @test */
    public function a_generated_snapshot_keeps_the_position_after_a_promotion()
    {
        $position = DB::table('tdh_user.designation')->where('id', 52)->value('description');
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 22000]);
        $this->draftJuly();
        app(CutoffSnapshotService::class)->generate($this->cutoffId, $this->userId);

        DB::table('tdh_user.users')->where('id', $this->userId)->update(['designation' => 53]);

        $this->assertSame($position, DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $this->cutoffId)->value('position'));
    }

    /** @test */
    public function the_payroll_view_and_export_use_the_lines_saved_with_the_payroll()
    {
        $this->seedCutoff();
        $this->addComponent('PERA', 'earning', false, 2000);
        $this->addComponent('GSIS_PS', 'deduction', true, 1980);
        $this->addComponent('PRIOR_PERIOD_REFUND', 'earning', true, 500);
        [$id, $response] = $this->compute();
        $response->assertOk();

        // Today's settings no longer matter: a new active component does not appear.
        DB::table('hris.payroll_components')->insert([
            'code' => 'TEST_LATER', 'name' => 'Added Later', 'type' => 'earning', 'is_global' => 1, 'taxable' => 0,
            'default_amount' => 100, 'calculation_type' => 'fixed', 'is_active' => 1, 'is_cutoff_adjustable' => 0, 'sort_order' => 1,
        ]);

        $view = $this->withoutMiddleware()->getJson('/get_payroll_employee?payroll_id=' . $id)->assertOk();
        $earnings = collect($view->json('earningComponents'))->pluck('code')->all();
        $deductions = collect($view->json('deductionComponents'))->pluck('code')->all();
        $this->assertSame(['PERA', 'PRIOR_PERIOD_REFUND'], $earnings);
        $this->assertSame(['GSIS_PS'], $deductions);
        $this->assertNotContains('WITHHOLDING_TAX', $deductions);

        $path = storage_path('app/test_payroll_export_' . uniqid() . '.xlsx');
        app(PayrollServices::class)->exportRegularPayroll(app(PayrollServices::class)->getPayrollEmployee($id), $path, (object) ['title' => 'Test', 'start_date' => '2027-07-01', 'end_date' => '2027-07-31']);
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $headers = [];
        for ($col = 1; ($value = $sheet->getCellByColumnAndRow($col, 4)->getValue()) !== null; $col++) {
            $headers[$value] = $col;
        }
        $this->assertArrayHasKey('PRIOR_PERIOD_REFUND', $headers);
        $this->assertArrayHasKey('GSIS_PS', $headers);
        $this->assertArrayNotHasKey('ADDED LATER', $headers);
        $this->assertArrayNotHasKey('STAT', $headers);
        $cell = fn($header) => (float) $sheet->getCellByColumnAndRow($headers[$header], 5)->getValue();
        $this->assertEquals(500, $cell('PRIOR_PERIOD_REFUND'));
        $this->assertEquals(1980, $cell('GSIS_PS'));

        // Every amount has a column, so the row adds up to net pay.
        $earned = $cell('BASIC PAY') - $cell('LATE / ABSENT / LWOP') + $cell('PERA') + $cell('PRIOR_PERIOD_REFUND');
        $this->assertEqualsWithDelta($earned - $cell('TOTAL DEDUCTION'), $cell('NET PAY'), 0.01);
    }

    /** @test */
    public function export_to_excel_downloads_even_when_the_exports_folder_is_missing()
    {
        $this->seedCutoff();
        $this->addComponent('PERA', 'earning', false, 2000);
        [$id, $response] = $this->compute();
        $response->assertOk();

        $folder = storage_path('app' . DIRECTORY_SEPARATOR . 'exports');
        if (is_dir($folder) && count(glob($folder . DIRECTORY_SEPARATOR . '*')) === 0) {
            rmdir($folder);
        }

        $download = $this->withoutMiddleware()->get('/payroll/export/' . $id);

        $download->assertOk();
        $this->assertStringContainsString('payroll_Snapshot_Payroll.xlsx', $download->headers->get('content-disposition'));
        $file = $download->baseResponse->getFile()->getPathname();
        $title = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet()->getCell('A1')->getValue();
        @unlink($file); // a test never sends the download, so it would stay behind
        $this->assertSame('PAYROLL SUMMARY - SNAPSHOT PAYROLL', $title);
    }

    /** @test */
    public function a_generated_snapshot_computes_end_to_end()
    {
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 44000]);
        DB::table('hris.employees')->updateOrInsert(['user_id' => $this->userId], ['employee_type' => 'Permanent']);
        $componentId = DB::table('hris.payroll_components')->insertGetId([
            'code' => 'TEST_PERA', 'name' => 'Pera', 'type' => 'earning', 'is_global' => 1, 'taxable' => 0,
            'default_amount' => 2000, 'calculation_type' => 'fixed', 'is_active' => 1, 'is_cutoff_adjustable' => 0, 'sort_order' => 1,
        ]);

        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'End To End ' . uniqid(), 'status' => 'draft', 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $this->cutoffId, 'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'total_working_days' => 22, 'days_present' => 0, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CutoffSnapshotService::class)->generate($this->cutoffId, $this->userId);
        DB::table('hris.cutoff_summaries_master')->where('id', $this->cutoffId)->update(['status' => 'finalized']);
        DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->cutoffId)->update(['days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0]);

        DB::table('hris.payroll_components')->where('id', $componentId)->update(['default_amount' => 9999]);

        [$id, $response] = $this->compute();
        $response->assertOk();
        $row = $this->result($id);

        $this->assertEquals(44000, (float) $row->basic_salary);
        $this->assertEquals(2000, (float) $row->other_earnings);
        $this->assertEqualsWithDelta(44000 + 2000 - 4400, (float) $row->net_pay, 0.01);
    }
}
