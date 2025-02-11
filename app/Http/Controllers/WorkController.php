<?php

namespace App\Http\Controllers;

use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkController extends Controller
{
    //
    public function getWork(Request $request)
    {
        try {
            $user_id = ($request->id == 0) ? Auth::id() : $request->id;
            $work = DB::table('work_experiences')
                ->where('work_experiences.user_id', '=', $user_id)
                ->select('work_experiences.*')
                ->orderBy('work_experiences.working_from', 'DESC')
                ->get();
            return response(['code' => 200, 'results' => $work]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateWork(Request $request)
    {
        try {
            // Check if another work entry already has is_current_work = 1 for this user
            $existingCurrentWork = Work::where('user_id', Auth::id())
                ->where('is_current_work', 1)
                ->where('id', '!=', $request->id) // Exclude the same record if updating
                ->first();

            if ($request->is_current_work == 1 && $existingCurrentWork) {
                return response()->json(['code' => 500, 'message' => 'There is already a current work status.']);
            }

            $work = Work::find($request->id);
            if (!$work) {
                $work = new Work;
                $work->user_id = Auth::id();
            }

            $work->working_from = $request->working_from;
            $work->working_to = $request->working_to;
            $work->position = $request->position;
            $work->agency = $request->agency;
            $work->monthly_salary = $request->monthly_salary;
            $work->salary_grade = $request->salary_grade;
            $work->appointment_status = $request->appointment_status;
            $work->is_government = $request->is_government;
            $work->is_current_work = $request->is_current_work;
            $work->save();
            return response(['code' => 200, 'message' => 'Work Experiences updated', 'id' => $work->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteWork(Request $request)
    {
        try {
            $work = Work::find($request->id);
            if (!$work) {
                return response(['code' => 500, 'message' => 'Work ID dont exists']);
            }
            $work->forceDelete();
            return response(['code' => 200, 'message' => 'Work Experiences deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
