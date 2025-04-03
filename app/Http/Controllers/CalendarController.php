<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    /**
     * Fetch all holidays for FullCalendar
     */
    public function getHolidays()
    {
        $holidays = DB::table('calendar_holidays')->get();

        $events = $holidays->map(function ($holiday) {
            return [
                'title' => "{$holiday->type} Holiday: {$holiday->description}",
                'start' => $holiday->date,
                'color' => $holiday->type === 'Regular' ? '#ff4d4d' : '#ffcc00', // Red for Regular, Yellow for Special
                'textColor' => '#ffffff',
                'type' => $holiday->type,
                'description' => $holiday->description
            ];
        });

        return response()->json($events);
    }

    /**
     * Store a new holiday
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'type' => 'required|string',
                'description' => 'required|string'
            ]);

            DB::table('calendar_holidays')->insert([
                'date' => $request->date,
                'type' => $request->type,
                'description' => $request->description,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['message' => 'Holiday added successfully'], 201);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    /**
     * Update holidays by date
     */
    public function update(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'type' => 'required|string',
            'description' => 'required|string'
        ]);

        $updated = DB::table('calendar_holidays')
            ->where('date', $request->date)
            ->update([
                'type' => $request->type,
                'description' => $request->description,
                'updated_at' => now()
            ]);

        if ($updated) {
            return response()->json(['message' => 'Holidays updated successfully'], 200);
        }

        return response()->json(['message' => 'No holidays found on this date'], 404);
    }

    /**
     * Delete holidays by date
     */
    public function destroy(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $deleted = DB::table('calendar_holidays')->where('date', $request->date)->delete();

        if ($deleted) {
            return response()->json(['message' => 'Holidays deleted successfully'], 200);
        }

        return response()->json(['message' => 'No holidays found on this date'], 404);
    }
}
