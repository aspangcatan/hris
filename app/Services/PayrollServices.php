<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollServices
{
    public function getPayrollEmployee($payroll_id)
    {
        $list = DB::table('hris.payroll_employees as e')
            ->join('tdh_user.users as u', 'u.id', '=', 'e.employee_id')
            ->join('tdh_user.division as d', 'd.id', '=', 'u.division')
            ->join('tdh_user.section as s', 's.id', '=', 'u.section')
            ->where('e.payroll_id', '=', $payroll_id)
            ->select(
                'e.*',
                'u.fname',
                'u.mname',
                'u.lname',
                'u.suffix',
                'd.description as division',
                's.description as section'
            )
            ->get();

        return $list;
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


    public function savePayrollEmployee($payroll_id, $employee,$totalLateUndertimeMinutes, $totalAbsentDays,$totalWorkingDays)
    {
        $salary = DB::table('hris.payroll_settings')
            ->where('userid', '=', $employee["userid"])
            ->first();

        $benefits = DB::table('hris.employee_benefits')
            ->where('userid', '=', $employee["userid"])
            ->first();

        $deductions = DB::table('hris.employee_deductions')
            ->where('userid', '=', $employee["userid"])
            ->first();


        $basic_salary = $salary->basic_salary ?? 0.00;
        $daily_rate = $basic_salary / $totalWorkingDays;
        $hourly_rate = $daily_rate / 8;
        $minute_rate = $hourly_rate / 60;

        $lateUndertimeDeduction = $totalLateUndertimeMinutes * $minute_rate;
        $absentDeduction = $totalAbsentDays * $daily_rate;
        $totalTardinessDeductions = $lateUndertimeDeduction + $absentDeduction;


        $pera = $benefits->pera ?? 0.00;
        $rata = $benefits->rata ?? 0.00;
        $subsistence = $benefits->subsistence ?? 0.00;
        $laundry = $benefits->laundry ?? 0.00;
        $longevity = $benefits->longevity ?? 0.00;
        $cellphone_allowance = $benefits->cellphone_allowance ?? 0.00;

        $gross_pay = ($basic_salary + $pera + $rata + $subsistence + $laundry + $longevity + $cellphone_allowance) - $totalTardinessDeductions;

        //Safely extract each deduction with null fallback
        $gsis_ps = $deductions->gsis_ps ?? 0.00;
        $gsis_gs = $deductions->gsis_gs ?? 0.00;
        $gsis_ec = $deductions->gsis_ec ?? 0.00;
        $hdmf = $deductions->hdmf ?? 0.00;
        $phic = $deductions->phic ?? 0.00;
        $pagibig_cal = $deductions->pagibig_cal ?? 0.00;
        $pagibig_mpl = $deductions->pagibig_mpl ?? 0.00;
        $pagibig_mp2 = $deductions->pagibig_mp2 ?? 0.00;
        $pagibig_mp2_2nd = $deductions->pagibig_mp2_2nd ?? 0.00;
        $pagibig_mp2_3rd = $deductions->pagibig_mp2_3rd ?? 0.00;
        $gsis_consoloan = $deductions->gsis_consoloan ?? 0.00;
        $gsis_emergency_loan = $deductions->gsis_emergency_loan ?? 0.00;
        $gsis_educational_loan = $deductions->gsis_educational_loan ?? 0.00;
        $gsis_policy_loan = $deductions->gsis_policy_loan ?? 0.00;
        $gsis_opt_life_loan = $deductions->gsis_opt_life_loan ?? 0.00;
        $gsis_gfal = $deductions->gsis_gfal ?? 0.00;
        $gsis_mpl = $deductions->gsis_mpl ?? 0.00;
        $gsis_cpl = $deductions->gsis_cpl ?? 0.00;
        $gsis_mpl_lite = $deductions->gsis_mpl_lite ?? 0.00;
        $cfi_coop = $deductions->cfi_coop ?? 0.00;
        $first_valley_bank = $deductions->first_valley_bank ?? 0.00;
        $tdhea = $deductions->tdhea ?? 0.00;
        $dbp_loan = $deductions->dbp_loan ?? 0.00;
        $ucpb_loan = $deductions->ucpb_loan ?? 0.00;
        $meds_payable = $deductions->meds_payable ?? 0.00;
        $sss_deduction = $deductions->sss_deduction ?? 0.00;

        $total_deductions = $gsis_ps + $gsis_gs + $gsis_ec + $hdmf + $phic +
            $pagibig_cal + $pagibig_mpl + $pagibig_mp2 + $pagibig_mp2_2nd + $pagibig_mp2_3rd +
            $gsis_consoloan + $gsis_emergency_loan + $gsis_educational_loan +
            $gsis_policy_loan + $gsis_opt_life_loan + $gsis_gfal + $gsis_mpl +
            $gsis_cpl + $gsis_mpl_lite + $cfi_coop + $first_valley_bank +
            $tdhea + $dbp_loan + $ucpb_loan + $meds_payable + $sss_deduction;

        return DB::table('hris.payroll_employees')->insertGetId([
            'payroll_id' => $payroll_id,
            'employee_id' => $employee['userid'],
            'division' => 1,
            'section' => 2,
            'basic_salary' => $basic_salary,
            'pera' => $pera,
            'rata' => $rata,
            'subsistence' => $subsistence,
            'laundry' => $laundry,
            'longevity' => $longevity,
            'cellphone_allowance' => $cellphone_allowance,
            'late_absent' => $totalTardinessDeductions,
            'gross_pay' => $gross_pay,
            'gsis_ps' => $gsis_ps,
            'gsis_gs' => $gsis_gs,
            'gsis_ec' => $gsis_ec,
            'hdmf' => $hdmf,
            'phic' => $phic,
            'pagibig_cal' => $pagibig_cal,
            'pagibig_mpl' => $pagibig_mpl,
            'pagibig_mp2' => $pagibig_mp2,
            'pagibig_mp2_2nd' => $pagibig_mp2_2nd,
            'pagibig_mp2_3rd' => $pagibig_mp2_3rd,
            'gsis_consoloan' => $gsis_consoloan,
            'gsis_emergency_loan' => $gsis_emergency_loan,
            'gsis_educational_loan' => $gsis_educational_loan,
            'gsis_policy_loan' => $gsis_policy_loan,
            'gsis_opt_life_loan' => $gsis_opt_life_loan,
            'gsis_gfal' => $gsis_gfal,
            'gsis_mpl' => $gsis_mpl,
            'gsis_cpl' => $gsis_cpl,
            'gsis_mpl_lite' => $gsis_mpl_lite,
            'cfi_coop' => $cfi_coop,
            'first_valley_bank' => $first_valley_bank,
            'tdhea' => $tdhea,
            'dbp_loan' => $dbp_loan,
            'ucpb_loan' => $ucpb_loan,
            'meds_payable' => $meds_payable,
            'sss_deduction' => $sss_deduction,
            'deductions' => $total_deductions,
            'net_pay' => $gross_pay - $total_deductions,
            'salary_adjustment' => $employee['salary_adjustment'],
            'hazard_adjustment' => $employee['hazard_adjustment'],
            'pera_adjustment' => $employee['pera_adjustment'],
            'subsistence_adjustment' => $employee['subsistence_adjustment'],
            'laundry_adjustment' => $employee['laundry_adjustment'],
            'created_at' => now()
        ]);
    }
}
