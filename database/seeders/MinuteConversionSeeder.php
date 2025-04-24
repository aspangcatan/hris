<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MinuteConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [];
        $now = Carbon::now();

        for ($i = 1; $i <= 60; $i++) {
            $data[] = [
                'id' => $i,
                'minute' => $i,
                'points_deduction' => round($i * 0.00208333, 6), // up to 6 decimal places
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('minute_conversions')->insert($data);
    }
}
