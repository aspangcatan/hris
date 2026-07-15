<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyEarnedPointsSeeder extends Seeder
{
    /**
     * Civil Service daily credit-earning table (per leave type — VL and SL
     * earn identically). LeaveCreditService applies this value to each
     * monthly_earned leave type separately, so "points" here is the
     * per-type (VL or SL) figure, not the combined VL+SL total.
     *
     * @return void
     */
    public function run()
    {
        $points = [
            1 => 0.042,  2 => 0.083,  3 => 0.125,  4 => 0.167,  5 => 0.208,
            6 => 0.250,  7 => 0.292,  8 => 0.333,  9 => 0.375,  10 => 0.417,
            11 => 0.458, 12 => 0.500, 13 => 0.542, 14 => 0.583, 15 => 0.625,
            16 => 0.667, 17 => 0.708, 18 => 0.750, 19 => 0.792, 20 => 0.833,
            21 => 0.875, 22 => 0.917, 23 => 0.958, 24 => 1.000, 25 => 1.042,
            26 => 1.083, 27 => 1.125, 28 => 1.167, 29 => 1.208, 30 => 1.250,
        ];

        $now = Carbon::now();
        $data = [];

        foreach ($points as $present => $value) {
            $data[] = [
                'id' => $present,
                'present' => $present,
                'points' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('monthly_earned_points')->insert($data);
    }
}
