<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyEarnedPointsSeeder extends Seeder
{
    /**
     * CSC Table III: leave credits earned in a month by an official/employee
     * without any vacation leave credit left, keyed by the number of days on
     * leave without pay (filed and approved LWOP). VL and SL earn identically;
     * LeaveCreditService applies this value to each monthly_earned leave type
     * separately, so "points" is the per-type (VL or SL) figure.
     *
     * @return void
     */
    public function run()
    {
        $points = [
            '0.00' => 1.250, '0.50' => 1.229, '1.00' => 1.208, '1.50' => 1.188, '2.00' => 1.167, '2.50' => 1.146,
            '3.00' => 1.125, '3.50' => 1.104, '4.00' => 1.083, '4.50' => 1.063, '5.00' => 1.042, '5.50' => 1.021,
            '6.00' => 1.000, '6.50' => 0.979, '7.00' => 0.958, '7.50' => 0.938, '8.00' => 0.917, '8.50' => 0.896,
            '9.00' => 0.875, '9.50' => 0.854, '10.00' => 0.833, '10.50' => 0.813, '11.00' => 0.792, '11.50' => 0.771,
            '12.00' => 0.750, '12.50' => 0.729, '13.00' => 0.708, '13.50' => 0.687, '14.00' => 0.667, '14.50' => 0.646,
            '15.00' => 0.625, '15.50' => 0.604, '16.00' => 0.583, '16.50' => 0.562, '17.00' => 0.542, '17.50' => 0.521,
            '18.00' => 0.500, '18.50' => 0.479, '19.00' => 0.458, '19.50' => 0.437, '20.00' => 0.417, '20.50' => 0.396,
            '21.00' => 0.375, '21.50' => 0.354, '22.00' => 0.333, '22.50' => 0.312, '23.00' => 0.292, '23.50' => 0.271,
            '24.00' => 0.250, '24.50' => 0.229, '25.00' => 0.208, '25.50' => 0.187, '26.00' => 0.167, '26.50' => 0.146,
            '27.00' => 0.125, '27.50' => 0.104, '28.00' => 0.083, '28.50' => 0.062, '29.00' => 0.042, '29.50' => 0.021,
            '30.00' => 0.000,
        ];

        $now = Carbon::now();
        $data = [];

        foreach ($points as $lwop => $value) {
            $data[] = [
                'lwop' => $lwop,
                'points' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('monthly_earned_points')->insert($data);
    }
}
