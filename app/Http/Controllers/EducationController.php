<?php

namespace App\Http\Controllers;

use App\Models\Education;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EducationController extends Controller
{
    public function getEducation(Request $request)
    {
        try {
            $user_id = ($request->id == 0) ? Auth::id() : $request->id;
            $educations = Education::where('user_id', $user_id)->get();
            return response(['code' => 200, 'results' => $educations]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateEducation(Request $request)
    {
        try {

            $education = Education::find($request->id);
            if (!$education) {
                $education = new Education;
                $education->user_id = Auth::id();
                $education->level = $request->level;
            }

            $education->school_name = $request->school_name;
            $education->education_degree = $request->education_degree;
            $education->attendance_from = $request->attendance_from;
            $education->attendance_to = $request->attendance_to;
            $education->highest_level = $request->highest_level;
            $education->year_graduated = $request->year_graduated;
            $education->scholarship = $request->scholarship;
            $education->save();
            return response(['code' => 200, 'message' => 'Education updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteEducation(Request $request)
    {
        try {
            $id = $request->id;
            $model = Education::where(function ($query) use ($id) {
                $query->where('id', $id);
                $query->where('user_id', Auth::id());
            })->first();

            if (!$model) return response()->json(['code' => 500, 'message' => 'Education data not found']);

            $model->forceDelete();
            return response()->json(['code' => 200, 'message' => 'Record deleted']);
        } catch (\Exception $exception) {
            return response()->json(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
