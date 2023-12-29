<?php

namespace App\Http\Controllers;

use App\Models\Learning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LearningController extends Controller
{
    public function getLearning(Request $request)
    {
        try {
            $user_id = ($request->id == 0)?Auth::id():$request->id;
            $learning = Learning::where('user_id', $user_id)
                ->orderBy('attendance_from', 'DESC')
                ->get();
            return response(['code' => 200, 'results' => $learning]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateLearning(Request $request)
    {
        try {

            $learning = Learning::find($request->id);
            if (!$learning) {
                $learning = new Learning;
                $learning->user_id = Auth::id();
            }
            $learning->learning_title = $request->learning_title;
            $learning->attendance_from = $request->attendance_from;
            $learning->attendance_to = $request->attendance_to;
            $learning->number_hours = $request->number_hours;
            $learning->learning_type = $request->learning_type;
            $learning->conducted_by = $request->conducted_by;
            $learning->save();
            return response(['code' => 200, 'message' => 'Learning & development updated', 'id' => $learning->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteLearning(Request $request)
    {
        try {
            $learning = Learning::find($request->id);
            if (!$learning) {
                return response(['code' => 500, 'message' => 'Learning ID dont exists']);
            }
            $learning->forceDelete();
            return response(['code' => 200, 'message' => 'Learning & development deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
