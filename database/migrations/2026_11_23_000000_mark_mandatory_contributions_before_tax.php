<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Employee shares of GSIS, PhilHealth and Pag-IBIG are excluded from taxable
 * compensation (NIRC Sec. 32(B)(7)(f), as amended by RA 10963), so they are
 * taken before withholding tax. On a deduction, payroll_components.taxable is
 * the "Before tax" flag. Employer shares (GSIS_GS, GSIS_EC) are left alone.
 *
 * Data only: finalized cutoffs keep the flags they were snapshotted with; a
 * cutoff picks this up when its snapshot is (re)generated.
 */
return new class extends Migration
{
    private const CODES = ['GSIS_PS', 'PHIC', 'HDMF'];

    public function up()
    {
        DB::table('hris.payroll_components')
            ->whereIn('code', self::CODES)
            ->where('type', 'deduction')
            ->update(['taxable' => 1, 'updated_at' => now()]);
    }

    public function down()
    {
        DB::table('hris.payroll_components')
            ->whereIn('code', self::CODES)
            ->where('type', 'deduction')
            ->update(['taxable' => 0, 'updated_at' => now()]);
    }
};
