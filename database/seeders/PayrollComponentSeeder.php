<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollComponentSeeder extends Seeder
{
    /**
     * Standing Philippine government payroll components (SSL-based allowances,
     * mandatory contributions, and standing loan deductions).
     *
     * Only PERA has a real default_amount — it is the one flat, universal
     * allowance (DBM Budget Circular, currently P2,000/month for all
     * government personnel). Every other component is salary/position/
     * eligibility-dependent (GSIS/PHIC/HDMF premiums are percentage-based,
     * RATA/HAZARD/SUBSISTENCE/LONGEVITY depend on plantilla position or
     * years of service, loan deductions depend on the individual's
     * outstanding balance) — those are seeded at default_amount = 0 and
     * is_global = false so nothing is silently charged; HR must assign the
     * actual per-employee amount via the Payroll Components admin page.
     */
    public function run(): void
    {
        $earnings = [
            ['code' => 'PERA',                 'name' => 'Personal Economic Relief Allowance', 'is_global' => true,  'taxable' => false, 'default_amount' => 2000, 'adjustable' => true],
            ['code' => 'RATA',                  'name' => 'Representation and Transportation Allowance', 'is_global' => false, 'taxable' => false, 'default_amount' => 0, 'adjustable' => false],
            ['code' => 'SUBSISTENCE',           'name' => 'Subsistence Allowance', 'is_global' => false, 'taxable' => false, 'default_amount' => 0, 'adjustable' => true],
            ['code' => 'LAUNDRY',               'name' => 'Laundry Allowance', 'is_global' => false, 'taxable' => false, 'default_amount' => 0, 'adjustable' => true],
            ['code' => 'HAZARD',                'name' => 'Hazard Pay', 'is_global' => false, 'taxable' => false, 'default_amount' => 0, 'adjustable' => true],
            ['code' => 'LONGEVITY',             'name' => 'Longevity Pay', 'is_global' => false, 'taxable' => true,  'default_amount' => 0, 'adjustable' => false],
            ['code' => 'CELLPHONE_ALLOWANCE',   'name' => 'Cellphone Allowance', 'is_global' => false, 'taxable' => false, 'default_amount' => 0, 'adjustable' => false],
        ];

        // 'adjustable' components get a quick per-cutoff entry field in the
        // Review & Compute modal (one signed amount: positive adds to the
        // deduction, negative refunds/credits it). See PayrollServices::
        // getComputedComponents() and PayrollController::getCutoffEmployeesForPayroll().
        $deductions = [
            ['code' => 'GSIS_PS',               'name' => 'GSIS Personal Share', 'adjustable' => true],
            ['code' => 'GSIS_GS',                'name' => 'GSIS GSIS Share (Employer)', 'adjustable' => true],
            ['code' => 'GSIS_EC',                'name' => 'GSIS Employees Compensation', 'adjustable' => false],
            ['code' => 'HDMF',                   'name' => 'Pag-IBIG (HDMF) Contribution', 'adjustable' => false],
            ['code' => 'PHIC',                    'name' => 'PhilHealth (PHIC) Contribution', 'adjustable' => true],
            ['code' => 'PAGIBIG_CAL',             'name' => 'Pag-IBIG Calamity Loan', 'adjustable' => false],
            ['code' => 'PAGIBIG_MPL',             'name' => 'Pag-IBIG Multi-Purpose Loan', 'adjustable' => false],
            ['code' => 'PAGIBIG_MP2',             'name' => 'Pag-IBIG MP2 Savings', 'adjustable' => false],
            ['code' => 'PAGIBIG_MP2_2ND',         'name' => 'Pag-IBIG MP2 Savings (2nd Account)', 'adjustable' => false],
            ['code' => 'PAGIBIG_MP2_3RD',         'name' => 'Pag-IBIG MP2 Savings (3rd Account)', 'adjustable' => false],
            ['code' => 'GSIS_CONSOLOAN',          'name' => 'GSIS Consolidated Loan', 'adjustable' => true],
            ['code' => 'GSIS_EMERGENCY_LOAN',     'name' => 'GSIS Emergency Loan', 'adjustable' => true],
            ['code' => 'GSIS_EDUCATIONAL_LOAN',   'name' => 'GSIS Educational Loan', 'adjustable' => false],
            ['code' => 'GSIS_POLICY_LOAN',        'name' => 'GSIS Policy Loan', 'adjustable' => false],
            ['code' => 'GSIS_OPT_LIFE_LOAN',      'name' => 'GSIS Optional Life Insurance Loan', 'adjustable' => false],
            ['code' => 'GSIS_GFAL',               'name' => 'GSIS General Financial Assistance Loan', 'adjustable' => false],
            ['code' => 'GSIS_MPL',                'name' => 'GSIS Multi-Purpose Loan', 'adjustable' => true],
            ['code' => 'GSIS_CPL',                'name' => 'GSIS Conso Policy Loan', 'adjustable' => false],
            ['code' => 'GSIS_MPL_LITE',           'name' => 'GSIS MPL Lite', 'adjustable' => false],
            ['code' => 'CFI_COOP',                'name' => 'CFI Cooperative', 'adjustable' => false],
            ['code' => 'FIRST_VALLEY_BANK',       'name' => '1st Valley Bank Loan', 'adjustable' => false],
            ['code' => 'TDHEA',                   'name' => 'TDHEA (Employees Association) Dues/Loan', 'adjustable' => false],
            ['code' => 'DBP_LOAN',                'name' => 'DBP Loan', 'adjustable' => false],
            ['code' => 'UCPB_LOAN',               'name' => 'UCPB Loan', 'adjustable' => false],
            ['code' => 'MEDS_PAYABLE',            'name' => 'Meds Payable', 'adjustable' => false],
            ['code' => 'SSS_DEDUCTION',           'name' => 'SSS Deduction', 'adjustable' => true],
        ];

        $sort = 0;

        foreach ($earnings as $component) {
            DB::table('hris.payroll_components')->updateOrInsert(
                ['code' => $component['code']],
                [
                    'name'                => $component['name'],
                    'type'                => 'earning',
                    'is_global'           => $component['is_global'],
                    'taxable'             => $component['taxable'],
                    'default_amount'      => $component['default_amount'],
                    'is_active'           => true,
                    'is_cutoff_adjustable' => $component['adjustable'],
                    'sort_order'          => $sort++,
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ]
            );
        }

        foreach ($deductions as $component) {
            DB::table('hris.payroll_components')->updateOrInsert(
                ['code' => $component['code']],
                [
                    'name'                => $component['name'],
                    'type'                => 'deduction',
                    'is_global'           => false,
                    'taxable'             => false,
                    'default_amount'      => 0,
                    'is_active'           => true,
                    'is_cutoff_adjustable' => $component['adjustable'],
                    'sort_order'          => $sort++,
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ]
            );
        }
    }
}
