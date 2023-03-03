<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function getSurvey(Request $request)
    {
        try {
            
            $surveys = Survey::where('user_id', Auth::id())->get();
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
                $survey = Survey::where([['user_id', Auth::id()], ['question', $surveys[$i]['question']]])->first();
                if (!$survey) {
                    $survey = new Survey;
                    $survey->user_id = Auth::id();
                    $survey->question = $surveys[$i]['question'];
                }

                $survey->answer = $surveys[$i]['answer'];
                $survey->details = $surveys[$i]['details'];
                $survey->extra = $surveys[$i]['extra'];
                $survey->save();
            }

            return response(['code' => 200, 'message' => 'Surveys updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
