<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Recognition;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtherInformationController extends Controller
{
    public function getOtherInformation(Request $request)
    {
        try {
            $user_id = ($request->id == 0)?Auth::id():$request->id;
            $skill = Skill::where('user_id', $user_id)->get();
            $recognition = Recognition::where('user_id', $user_id)->get();
            $membership = Membership::where('user_id', $user_id)->get();
            return response(['code' => 200, 'results' => ['skill' => $skill, 'recognition' => $recognition, 'membership' => $membership]]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateSkill(Request $request)
    {
        try {

            $skill = Skill::find($request->id);
            if (!$skill) {
                $skill = new Skill;
                $skill->user_id = Auth::id();
            }
            $skill->skills = $request->skills;
            $skill->save();
            return response(['code' => 200, 'message' => 'Skills updated', 'id' => $skill->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateRecognition(Request $request)
    {
        try {

            $recognition = Recognition::find($request->id);
            if (!$recognition) {
                $recognition = new Recognition;
                $recognition->user_id = Auth::id();
            }
            $recognition->recognition = $request->recognition;
            $recognition->save();
            return response(['code' => 200, 'message' => 'Recognition updated', 'id' => $recognition->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateMembership(Request $request)
    {
        try {

            $membership = Membership::find($request->id);
            if (!$membership) {
                $membership = new Membership;
                $membership->user_id = Auth::id();
            }
            $membership->association = $request->association;
            $membership->save();
            return response(['code' => 200, 'message' => 'Membership updated', 'id' => $membership->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }


    public function deleteSkill(Request $request)
    {
        try {
            $skill = Skill::find($request->id);
            if(!$skill){
                return response(['code'=>500,'message'=>'Skill ID dont exists']);
            }
            $skill->forceDelete();
            return response(['code'=>200,'message'=>'Skill deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteRecognition(Request $request)
    {
        try {
            $recognition = Recognition::find($request->id);
            if(!$recognition){
                return response(['code'=>500,'message'=>'Recognition ID dont exists']);
            }
            $recognition->forceDelete();
            return response(['code'=>200,'message'=>'Recognition deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteMembership(Request $request)
    {
        try {
            $membership = Membership::find($request->id);
            if(!$membership){
                return response(['code'=>500,'message'=>'Membership ID dont exists']);
            }
            $membership->forceDelete();
            return response(['code'=>200,'message'=>'Membersip deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
