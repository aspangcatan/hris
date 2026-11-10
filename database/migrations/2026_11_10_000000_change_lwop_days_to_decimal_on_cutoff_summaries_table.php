<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * lwop_days can now be fractional: a half-day LWOP counts 0.5
 * (CSC MC 41 s. 1998, Rule XVI, Sec. 33 handling in DTRServices::getLwopDaysByRange).
 * Existing whole numbers are kept as is (1 -> 1.000).
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE cutoff_summaries MODIFY lwop_days DECIMAL(6,3) NOT NULL DEFAULT 0');
    }

    public function down()
    {
        DB::statement('ALTER TABLE cutoff_summaries MODIFY lwop_days INT NOT NULL DEFAULT 0');
    }
};
