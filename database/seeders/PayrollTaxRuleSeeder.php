<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollTaxRuleSeeder extends Seeder
{
    /**
     * BIR TRAIN Law (RA 10963) revised annual withholding tax brackets,
     * per RR No. 11-2018 as amended, effective January 1, 2023 onward.
     *
     * PayrollServices::computeWithholdingTax() sums, for each bracket the
     * annual gross has passed through, (min(gross, max) - min) * rate% —
     * i.e. marginal/progressive computation, so each row's rate_percent
     * is that bracket's own marginal rate (no separate base-tax column
     * needed).
     *
     * VERIFY before relying on this for real disbursement: confirm these
     * are still the brackets currently registered with BIR for this agency
     * at the time payroll is run — tax law can be amended.
     */
    public function run(): void
    {
        $purpose = 'Payroll Monthly Salary';

        $brackets = [
            ['min' => 0,       'max' => 250000,   'rate' => 0],
            ['min' => 250000,  'max' => 400000,   'rate' => 15],
            ['min' => 400000,  'max' => 800000,   'rate' => 20],
            ['min' => 800000,  'max' => 2000000,  'rate' => 25],
            ['min' => 2000000, 'max' => 8000000,  'rate' => 30],
            ['min' => 8000000, 'max' => null,     'rate' => 35],
        ];

        $sort = 0;

        foreach ($brackets as $bracket) {
            DB::table('hris.payroll_tax_rules')->updateOrInsert(
                [
                    'purpose'    => $purpose,
                    'min_annual' => $bracket['min'],
                ],
                [
                    'max_annual'   => $bracket['max'],
                    'rate_percent' => $bracket['rate'],
                    'tax_mode'     => 'progressive',
                    'is_active'    => true,
                    'sort_order'   => $sort++,
                    'remarks'      => 'BIR TRAIN Law (RA 10963) table, RR 11-2018 as amended, effective 2023 — verify still current before real use.',
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
        }
    }
}
