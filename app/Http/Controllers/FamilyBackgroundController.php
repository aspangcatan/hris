<?php

namespace App\Http\Controllers;

use App\Models\FamilyBackground;
use Illuminate\Http\Request;

class FamilyBackgroundController extends Controller
{
    //
    public function getFamilyBackground(Request $request)
    {
        try {
            $user_id = 1116;
            $family_background = FamilyBackgroundController::where('user_id', $user_id)->get();
            return response(['code' => 200, 'results' => $family_background]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateFamilyBackground(Request $request)
    {
        try {
            $user_id = 1116;
            $family_background = FamilyBackgroundController::where('user_id', $user_id)->first();
            if (!$family_background) {
                $family_background = new FamilyBackground;
                $family_background->user_id = $user_id;
            }

            $family_background->spouse_fname = $request->spouse_fname;
            $family_background->spouse_mname = $request->spouse_mname;
            $family_background->spouse_lname = $request->spouse_lname;
            $family_background->spouse_ext_name = $request->spouse_ext_name;
            $family_background->spouse_occupation = $request->spouse_occupation;
            $family_background->spouse_employer = $request->spouse_employer;
            $family_background->spouse_business_address = $request->spouse_business_address;
            $family_background->spouse_contact_no = $request->spouse_contact_no;
            $family_background->father_fname = $request->father_fname;
            $family_background->father_mname = $request->father_mname;
            $family_background->father_lname = $request->father_lname;
            $family_background->father_ext_name = $request->father_ext_name;
            $family_background->mother_fname = $request->mother_fname;
            $family_background->mother_mname = $request->mother_mname;
            $family_background->mother_lname = $request->mother_lname;
            $family_background->mother_ext_name = $request->mother_ext_name;
            $family_background->save();
            return response(['code' => 200, 'message' => 'Family Backgrounds updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
