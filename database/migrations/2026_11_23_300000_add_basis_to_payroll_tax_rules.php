<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tax rules get a basis: 'annual' brackets are applied to monthly taxable x 12
 * (then / 12), as before; 'monthly' brackets apply to the monthly taxable as-is.
 * min_annual/max_annual hold the bracket amounts in the rule's basis.
 *
 * Adds the BIR monthly withholding tax table (RR 11-2018 Annex E as amended,
 * TRAIN second schedule, effective January 1, 2023 onward) as purpose
 * "BIR Monthly Withholding Tax". Marginal (progressive) slices reproduce the
 * table's base amounts exactly: 1,875.00 / 8,541.80 / 33,541.80 / 183,541.80.
 * Edit the values on the Taxation page when BIR changes them.
 */
return new class extends Migration
{
    private const PURPOSE = 'BIR Monthly Withholding Tax';

    public function up()
    {
        Schema::table('payroll_tax_rules', function (Blueprint $table) {
            $table->enum('basis', ['annual', 'monthly'])->default('annual')->after('tax_mode');
        });

        if (DB::table('payroll_tax_rules')->where('purpose', self::PURPOSE)->exists()) {
            return;
        }
        $brackets = [[0, 20833, 0], [20833, 33333, 15], [33333, 66667, 20], [66667, 166667, 25], [166667, 666667, 30], [666667, null, 35]];
        foreach ($brackets as $sort => [$min, $max, $rate]) {
            DB::table('payroll_tax_rules')->insert([
                'purpose' => self::PURPOSE,
                'min_annual' => $min,
                'max_annual' => $max,
                'rate_percent' => $rate,
                'tax_mode' => 'progressive',
                'basis' => 'monthly',
                'is_active' => 1,
                'sort_order' => $sort,
                'remarks' => 'BIR monthly withholding tax table, RR 11-2018 Annex E as amended (2023 onward).',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        DB::table('payroll_tax_rules')->where('purpose', self::PURPOSE)->delete();
        Schema::table('payroll_tax_rules', function (Blueprint $table) {
            $table->dropColumn('basis');
        });
    }
};
