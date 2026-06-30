<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SurveyController extends Controller
{
    public function getSurvey(Request $request)
    {
        try {
            $user_id = ($request->id == 0) ? Auth::id() : $request->id;
            $surveys = Survey::where('user_id', $user_id)->get();
            return response(['code' => 200, 'results' => $surveys]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateSurveys(Request $request)
    {
        try {

            $surveys = $request->surveys;
            for ($i = 0; $i < count($surveys); $i++) {
                Survey::updateOrCreate(
                    [
                        'user_id' => Auth::id(),
                        'question' => $surveys[$i]['question'],
                    ],
                    [
                        'answer' => $surveys[$i]['answer'],
                        'details' => $surveys[$i]['details'],
                        'extra' => $surveys[$i]['extra'],
                    ]
                );
            }

            return response(['code' => 200, 'message' => 'Surveys updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
