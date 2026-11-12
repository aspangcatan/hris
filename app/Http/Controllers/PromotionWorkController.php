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
            $work = PromotionWork::where('id', $request->id)->where('user_id', Auth::id())->first();
            $is_new = !$work;
            if ($is_new) {
                $work = new PromotionWork;
                $work->user_id = Auth::id();
            }
            $work->position_applied = $request->position_applied;
            $work->date_applied = $request->date_applied;
            $work->save();
            return response([
                'code' => 200,
                'message' => $is_new ? 'Application saved' : 'Application updated',
                'id' => $work->id,
            ]);
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
            })
                ->select('promotion_work_experience.*')
                ->selectSub(
                    RelatedWork::selectRaw('COUNT(*)')->whereColumn('related_work.work_id', 'promotion_work_experience.id'),
                    'related_count'
                )
                ->orderBy('date_applied', 'desc')
                ->get();
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /** Deletes one related work experience entry. */
    public function delete(Request $request)
    {
        try {
            $work = RelatedWork::find($request->id);
            if ($work && $this->ownsApplication($work->work_id)) {
                $this->deleteRelatedChildren($work->id);
                $work->forceDelete();
            }
            return response(['code' => 200, 'message' => 'Work experience removed']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /** Deletes a whole position application, including its related work experience. */
    public function deletePosition(Request $request)
    {
        try {
            $work = PromotionWork::where('id', $request->id)->where('user_id', Auth::id())->first();
            if ($work) {
                foreach (RelatedWork::where('work_id', $work->id)->get() as $related) {
                    $this->deleteRelatedChildren($related->id);
                    $related->forceDelete();
                }
                $work->delete();
            }
            return response(['code' => 200, 'message' => 'Application deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertRelatedWork(Request $request)
    {
        try {
            if (!$this->ownsApplication($request->work_id)) {
                return response(['code' => 500, 'message' => 'Application not found']);
            }

            $related_work = RelatedWork::where('id', $request->id)->where('work_id', $request->work_id)->first();
            $is_new = !$related_work;
            if ($is_new) {
                $related_work = new RelatedWork();
                $related_work->work_id = $request->work_id;
            }
            $related_work->duration = $request->duration;
            $related_work->position = $request->position;
            $related_work->office_unit = $request->office;
            $related_work->supervisor = $request->supervisor;
            $related_work->agency = $request->agency;
            $related_work->save();

            $related_work_id = $related_work->id;

            // Always replace the lists so removing every item actually clears them.
            $this->deleteRelatedChildren($related_work_id);
            foreach ($this->cleanList($request->accomplishments) as $a) {
                $item = new RelatedAccomplishment;
                $item->related_id = $related_work_id;
                $item->description = $a;
                $item->save();
            }
            foreach ($this->cleanList($request->duties) as $d) {
                $item = new RelatedDuties;
                $item->related_id = $related_work_id;
                $item->description = $d;
                $item->save();
            }

            return response([
                'code' => 200,
                'message' => $is_new ? 'Work experience added' : 'Work experience updated',
                'id' => $related_work_id,
            ]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getRelatedWork(Request $request)
    {
        try {
            if (!$this->ownsApplication($request->work_id)) {
                return response(['code' => 200, 'results' => []]);
            }
            $results = RelatedWork::where('work_id', $request->work_id)->orderBy('id')->get();

            for ($i = 0; $i < count($results); $i++) {
                $results[$i]->accomplishment = RelatedAccomplishment::where('related_id', $results[$i]->id)->get(['description']);
                $results[$i]->duties = RelatedDuties::where('related_id', $results[$i]->id)->get(['description']);
            }
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    private function ownsApplication($work_id): bool
    {
        return PromotionWork::where('id', $work_id)->where('user_id', Auth::id())->exists();
    }

    private function deleteRelatedChildren($related_id): void
    {
        RelatedAccomplishment::where('related_id', $related_id)->delete();
        RelatedDuties::where('related_id', $related_id)->delete();
    }

    private function cleanList($items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(function ($v) { return trim((string) $v); })
            ->filter(function ($v) { return $v !== ''; })
            ->values()
            ->all();
    }
}
