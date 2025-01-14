<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DtrLogsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [];
        $currentMonth = now()->format('Y-m'); // Get the current month (e.g., "2025-01")
        $daysInMonth = now()->daysInMonth;   // Get the total number of days in the current month

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = "{$currentMonth}-" . str_pad($day, 2, '0', STR_PAD_LEFT);

            // Generate random times
            $hourIn = rand(8, 10); // Random hour between 8 AM and 10 AM
            $minuteIn = rand(0, 59); // Random minute
            $dateTimeIn = Carbon::createFromFormat('Y-m-d H:i', "$date $hourIn:" . str_pad($minuteIn, 2, '0', STR_PAD_LEFT));

            $hoursWorked = rand(8, 10); // Random work duration (8–10 hours)
            $minutesWorked = rand(0, 59); // Random minutes
            $dateTimeOut = (clone $dateTimeIn)->addHours($hoursWorked)->addMinutes($minutesWorked);

            $data[] = [
                'userid' => 1116,
                'date_timein' => $dateTimeIn,
                'date_timeout' => $dateTimeOut,
                'type' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('dtr_logs')->insert($data);
    }
}
