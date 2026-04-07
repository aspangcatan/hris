<?php

namespace App\Http\Controllers;

use App\Models\PromotionWork;
use App\Models\RelatedAccomplishment;
use App\Models\RelatedDuties;
use App\Models\RelatedWork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionWorkController extends Controller
{
    public function store(Request $request)
    {
        try {
            $work = PromotionWork::where('id', $request->id)->first();
            if (!$work) {
                $work = new PromotionWork;
                $work->user_id = Auth::id();
            }
            $work->position_applied = $request->position_applied;
            $work->date_applied = $request->date_applied;
            $work->save();
            return response(['code' => 200, 'message' => 'New item added']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function show(Request $request)
    {
        try {
            $search = $request->search_key;
            $results = PromotionWork::where(function ($query) use ($search) {
                $query->where('user_id', '=', Auth::id());
                if ($search != "") {
                    $query->where('position_applied', 'LIKE', '%' . $search . '%');
                }
            })->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function delete(Request $request)
    {
        try {
            $work = RelatedWork::find($request->id);
            if ($work) {
                $work->forceDelete();
            }
            return response(['code' => 200, 'message' => 'Item deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertRelatedWork(Request $request)
    {
        try {

            $related_work = RelatedWork::where('id', $request->id)->first();
            if (!$related_work) {
                $related_work = new RelatedWork();
                $related_work->work_id = $request->work_id;
            }
            $related_work->duration = $request->duration;
            $related_work->position = $request->position;
            $related_work->office_unit = $request->office;
            $related_work->supervisor = $request->supervisor;
            $related_work->agency = $request->agency;
            $related_work->save();

            if ($request->id == 0) {
                $related_work_id = $related_work->id;
            } else {
                $related_work_id = $request->id;
            }

            $accomplishment = $request->accomplishments;
            $duties = $request->duties;

            if (count($accomplishment) > 0) {
                $accomplishment_del = RelatedAccomplishment::where('related_id',$related_work_id);
                $accomplishment_del->forceDelete();
                foreach ($accomplishment as $a) {
                    $accomplishment = new RelatedAccomplishment;
                    $accomplishment->related_id = $related_work_id;
                    $accomplishment->description = $a;
                    $accomplishment->save();
                }
            }

            if (count($duties) > 0) {
                $duties_del = RelatedDuties::where('related_id',$related_work_id);
                $duties_del->forceDelete();
                foreach ($duties as $a) {
                    $duties = new RelatedDuties;
                    $duties->related_id = $related_work_id;
                    $duties->description = $a;
                    $duties->save();
                }
            }

            return response(['code' => 200, 'message' => 'New item added', 'id']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getRelatedWork(Request $request)
    {
        try {
            $results = RelatedWork::where('work_id', $request->work_id)->get();

            for ($i = 0; $i < count($results); $i++) {
                $related_accomplishment = RelatedAccomplishment::where('related_id', $results[$i]->id)->get(['description']);
                $results[$i]->accomplishment = $related_accomplishment;

                $related_duties = RelatedDuties::where('related_id', $results[$i]->id)->get(['description']);
                $results[$i]->duties = $related_duties;
            }
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
