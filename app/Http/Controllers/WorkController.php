<?php

namespace App\Http\Controllers;

use App\Models\Work;
use Illuminate\Http\Request;

class WorkController extends Controller
{
    //
    public function getWork(Request $request)
    {
        try {
            
            $work = Work::where('user_id', Auth::id())->get();
            return response(['code' => 200, 'results' => $work]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateWork(Request $request)
    {
        try {
            
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
            return response(['code' => 200, 'message' => 'Work Experiences updated','id'=>$work->id]);
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
