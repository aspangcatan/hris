<?php

namespace Tests\Feature;

use App\Services\CutoffSnapshotService;
use App\Services\LeaveCreditService;
use App\Services\MinuteConversionService;
use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 8 reconciliation. Each case runs identical attendance through:
 *  - the snapshot path (generate, finalize, compute);
 *  - the live path (a cutoff with no snapshot), which must give the same pay;
 *  - the formula as it was at commit 196cd33, kept here for comparison, so every
 *    difference is a known, approved change.
 * Set RECON_REPORT to a file path to write the numbers out. Every write rolls back.
 */
class CutoffReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private const BASIC = 22000.0;
    private const PERA = 2000.0;
    private const GSIS = 1980.0;
    private const START = '2027-07-01';
    private const END = '2027-07-31';
    private const TARGET = '2027-07-06'; // a Tuesday: a work day on both schedules
    private const PURPOSE = 'Payroll Monthly Salary';

    private int $userId = 9831;
    private int $vl;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'hris.cutoff_snapshots_enabled' => true]);
        app('url')->forceRootUrl('http://localhost');

        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId, 'fname' => 'Recon', 'lname' => 'Case', 'username' => 'recon' . $this->userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52,
        ]);
        DB::table('hris.employees')->updateOrInsert(['user_id' => $this->userId], ['employee_type' => 'Permanent']);
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => self::BASIC]);
        DB::table('hris.calendar_holidays')->whereBetween('date', [self::START, self::END])->delete();

        DB::table('hris.payroll_components')->update(['is_active' => 0]);
        DB::table('hris.payroll_components')->insert([
            'code' => 'RECON_PERA', 'name' => 'Pera', 'type' => 'earning', 'is_global' => 1, 'taxable' => 0,
            'default_amount' => self::PERA, 'calculation_type' => 'fixed', 'is_active' => 1, 'is_cutoff_adjustable' => 0, 'sort_order' => 1,
        ]);
        $gsis = DB::table('hris.payroll_components')->insertGetId([
            'code' => 'RECON_GSIS', 'name' => 'Gsis', 'type' => 'deduction', 'is_global' => 0, 'taxable' => 0,
            'default_amount' => 0, 'calculation_type' => 'fixed', 'is_active' => 1, 'is_cutoff_adjustable' => 0, 'sort_order' => 2,
        ]);
        DB::table('hris.employee_payroll_components')->insert([
            'payroll_component_id' => $gsis, 'userid' => $this->userId, 'amount' => self::GSIS, 'is_active' => 1, 'is_excluded' => 0,
        ]);

        $this->vl = (int) DB::table('hris.leave_types')->where('code', 'VL')->value('id');

        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    protected function tearDown(): void
    {
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function cases(): array
    {
        return [
            '8-hour, perfect attendance' => ['8h', [], 0.0],
            '8-hour, one unfiled absence' => ['8h', ['absent' => true], 1000.0],
            '8-hour, one day LWOP' => ['8h', ['lwop' => 8], 1000.0],
            '8-hour, half-day LWOP' => ['8h', ['lwop' => 4], 500.0],
            '8-hour, 30 late + 15 undertime' => ['8h', ['late' => true], 93.75],
            '8-hour, 60-minute personal pass slip' => ['8h', ['slip' => true], 125.0],
            '8-hour, one day paid VL' => ['8h', ['paid' => true], 0.0],
            '10-hour, perfect attendance' => ['10h', [], 0.0],
            '10-hour, one unfiled absence' => ['10h', ['absent' => true], 1250.0],
            '10-hour, one day LWOP' => ['10h', ['lwop' => 10], 1250.0],
        ];
    }

    /**
     * @test
     * @dataProvider cases
     */
    public function the_snapshot_and_live_paths_agree_and_differences_from_the_old_formula_are_known(string $shift, array $case, float $expectedCharge)
    {
        $this->arrange($shift, $case);

        $snapshotCutoff = $this->draft('Recon snapshot');
        app(CutoffSnapshotService::class)->generate($snapshotCutoff, $this->userId);
        $this->finalize($snapshotCutoff);
        $snapshot = $this->compute($snapshotCutoff);

        $liveCutoff = $this->draft('Recon live');
        app(PayrollServices::class)->generateCutoffSummary($this->userId, self::START, self::END, $this->titleOf($liveCutoff), $this->userId);
        $this->finalize($liveCutoff);
        $live = $this->compute($liveCutoff);

        foreach (['basic_salary', 'other_earnings', 'late_absent', 'gross_pay', 'deductions', 'net_pay', 'first_half_10th', 'second_half_25th'] as $column) {
            $this->assertEqualsWithDelta((float) $live->$column, (float) $snapshot->$column, 0.01, "{$column} differs between the live and snapshot paths");
        }
        $this->assertEqualsWithDelta($expectedCharge, (float) $snapshot->late_absent, 0.01, 'charge');

        $summary = DB::table('hris.cutoff_summaries')->where('cutoff_id', $snapshotCutoff)->first();
        $credits = app(LeaveCreditService::class);
        $table3 = $credits->earnedPointsForLwop((float) $summary->credit_without_pay_days);
        $this->assertEqualsWithDelta($table3, (float) $summary->vl_earned, 0.0005);
        $this->assertEqualsWithDelta($table3, (float) $summary->sl_earned, 0.0005);

        $old = $this->oldFormula($summary, $table3);
        // Every difference is explained by an approved change.
        $this->assertEqualsWithDelta(
            $old['net'] + self::PERA - ((float) $snapshot->late_absent - $old['charge']) - ($this->tax($snapshot) - $old['tax']),
            (float) $snapshot->net_pay,
            0.01,
            'net pay difference is not fully explained'
        );

        $this->report($shift, $case, $summary, $snapshot, $old, $table3);
    }

    private function arrange(string $shift, array $case): void
    {
        $eight = $shift === '8h';
        $in = $eight ? '08:00:00' : '07:00:00';
        $out = $eight ? '17:00:00' : '18:00:00';
        $shiftId = DB::table('hris.schedule_master_lists')->insertGetId([
            'label' => 'Recon', 'code' => 'RECON', 'type' => 'FIXED', 'time_in' => $in, 'time_in_latest' => $in, 'time_out' => $out,
            'time_out_latest' => $out, 'break_out' => '12:00:00', 'break_in' => '13:00:00',
        ]);
        $off = (int) DB::table('hris.schedule_master_lists')->where('code', 'OFF')->value('id');

        $half = ($case['lwop'] ?? 0) === 4;
        $skip = !empty($case['absent']) || !empty($case['paid']) || (isset($case['lwop']) && !$half);

        for ($day = \Carbon\Carbon::parse(self::START); $day->lte(self::END); $day->addDay()) {
            $date = $day->toDateString();
            $isWork = $day->dayOfWeekIso <= ($eight ? 5 : 4);
            DB::table('hris.schedules')->insert([
                'employee_id' => $this->userId, 'schedule_id' => $isWork ? $shiftId : $off, 'schedule_date' => $date,
                'updated_by' => $this->userId, 'created_at' => now(),
            ]);
            if (!$isWork || ($date === self::TARGET && $skip)) {
                continue;
            }
            $logIn = "{$date} {$in}";
            $logOut = "{$date} {$out}";
            if ($date === self::TARGET && !empty($case['late'])) {
                $logIn = "{$date} 08:30:00";
                $logOut = "{$date} 16:45:00";
            }
            if ($date === self::TARGET && $half) {
                $logOut = "{$date} 12:00:00";
            }
            DB::table('hris.dtr_logs')->insert([
                ['userid' => $this->userId, 'status' => 'IN', 'created_at' => $logIn],
                ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => $logOut],
            ]);
        }

        if (!empty($case['slip'])) {
            DB::table('hris.pass_slips')->insert([
                'user_id' => $this->userId, 'slip_date' => self::TARGET, 'time_out' => '14:00:00', 'time_in' => '15:00:00',
                'type' => 'personal', 'status' => 'approved', 'created_at' => now(),
            ]);
        }
        if (isset($case['lwop']) || !empty($case['paid'])) {
            $appId = DB::table('hris.leave_applications')->insertGetId([
                'user_id' => $this->userId, 'status' => 'approved', 'is_lwop' => isset($case['lwop']) ? 1 : 0,
                'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
            ]);
            DB::table('hris.leave_date_applied')->insert([
                'leave_application_id' => $appId, 'leave_type_id' => $this->vl, 'date_applied' => self::TARGET,
                'hours' => $case['lwop'] ?? 8, 'status' => 'active', 'credits_deducted' => isset($case['lwop']) ? 0 : 1,
            ]);
        }
    }

    private function draft(string $title): int
    {
        $id = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => $title . ' ' . uniqid(), 'status' => 'draft', 'period_start' => self::START, 'period_end' => self::END,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $id, 'period_start' => self::START, 'period_end' => self::END,
            'total_working_days' => 22, 'days_present' => 0, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function titleOf(int $id): string
    {
        return (string) DB::table('hris.cutoff_summaries_master')->where('id', $id)->value('title');
    }

    private function finalize(int $id): void
    {
        DB::table('hris.cutoff_summaries_master')->where('id', $id)->update(['status' => 'finalized']);
    }

    private function compute(int $cutoffId): object
    {
        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $cutoffId, 'title' => 'Recon', 'start_date' => self::START, 'end_date' => self::END,
            'cutoff_start_date' => self::START, 'cutoff_end_date' => self::END, 'payroll_type' => 'Regular',
            'status' => 'draft', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId, 'employees' => [['userid' => $this->userId]],
        ])->assertOk();

        return DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->first();
    }

    private function tax(object $row): float
    {
        return (float) DB::table('hris.payroll_employee_components')
            ->where(['payroll_employee_id' => $row->id, 'code' => 'WITHHOLDING_TAX'])->value('amount');
    }

    /** PayrollServices::savePayrollEmployee and the credit formula as of commit 196cd33. */
    private function oldFormula(object $summary, float $table3): array
    {
        $daily = self::BASIC / max((int) $summary->total_working_days, 1);
        $minute = $daily / 8 / 60;
        $charge = ((int) $summary->late_minutes + (int) $summary->undertime_minutes) * $minute + (int) $summary->days_absent * $daily;
        $taxable = self::BASIC - $charge; // PERA is non-taxable, and was left out of net

        $method = new \ReflectionMethod(PayrollServices::class, 'computeWithholdingTax');
        $method->setAccessible(true);
        $tax = $method->invoke(app(PayrollServices::class), $taxable * 12, self::PURPOSE) / 12;

        $vlMinutes = (int) $summary->late_minutes + (int) $summary->undertime_minutes + (int) $summary->approved_pass_slips_minutes;

        return [
            'charge' => $charge,
            'tax' => $tax,
            'net' => $taxable - self::GSIS - $tax,
            'vl' => round($table3 - app(MinuteConversionService::class)->computeDeduction($vlMinutes), 3),
            'sl' => $table3,
        ];
    }

    private function report(string $shift, array $case, object $summary, object $new, array $old, float $table3): void
    {
        $path = getenv('RECON_REPORT');
        if (!$path) {
            return;
        }
        file_put_contents($path, json_encode([
            'case' => $this->dataName(),
            'old_charge' => round($old['charge'], 2),
            'new_charge' => round((float) $new->late_absent, 2),
            'old_tax' => round($old['tax'], 2),
            'new_tax' => round($this->tax($new), 2),
            'old_net' => round($old['net'], 2),
            'new_net' => round((float) $new->net_pay, 2),
            'old_vl' => $old['vl'],
            'new_vl' => $table3,
            'sl' => $old['sl'],
            'absent' => (int) $summary->days_absent,
            'lwop' => (float) $summary->lwop_days,
            'late' => (int) $summary->late_minutes,
            'ut' => (int) $summary->undertime_minutes,
            'slip' => (int) $summary->approved_pass_slips_minutes,
        ]) . PHP_EOL, FILE_APPEND);
    }
}
