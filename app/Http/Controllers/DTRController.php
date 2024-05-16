<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DTRController extends Controller
{
    public function store(Request $request)
    {
        try {
            DB::table('temp_dtr_logs')->updateOrInsert(
                [
                    'user_id' => $request->user_id,
                    'month' => $request->month,
                    'year' => $request->year
                ],
                [
                    'absent' => $request->absent,
                    'late' => $request->late
                ]);
            return response()->json(['message' => "DTR stored successfully"]);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $user_id = $request->user_id;
            $year = $request->year;
            $list = DB::table('temp_dtr_logs')
                ->where(function ($query) use ($user_id, $year) {
                    $query->where('user_id', '=', $user_id);
                    $query->where('year', '=', $year);
                })
                ->orderBy('month', 'ASC')
                ->get();

            return response()->json($list);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            DB::table('temp_dtr_logs')
                ->where('id', '=', $request->id)
                ->delete();
            return response()->json(['message' => 'DTR successfully deleted']);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }
}
