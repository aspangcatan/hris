<?php

namespace App\Http\Controllers;

use App\Models\CivilService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CivilServiceController extends Controller
{
    public function getCivilService(Request $request)
    {
        try {

            $civil_service = CivilService::where('user_id', Auth::id())->get();
            return response(['code' => 200, 'results' => $civil_service]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateCivilService(Request $request)
    {
        try {

            $civil_service = CivilService::find($request->id);
            if (!$civil_service) {
                $civil_service = new CivilService;
                $civil_service->user_id = Auth::id();
            }

            $civil_service->career_service = $request->career_service;
            $civil_service->rating = $request->rating;
            $civil_service->examination_date = $request->examination_date;
            $civil_service->examination_place = $request->examination_place;
            $civil_service->license_no = $request->license_no;
            $civil_service->license_expiry = $request->license_expiry;
            $civil_service->save();
            return response(['code' => 200, 'message' => 'Civil service updated','id'=>$civil_service->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteCivilService(Request $request)
    {
        try {
            $civil_service = CivilService::find($request->id);
            if (!$civil_service) {
                return response(['code' => 500, 'message' => 'Civil Service ID dont exists']);
            }
            $civil_service->forceDelete();
            return response(['code' => 200, 'message' => 'Civil service deleted']);
        } catch (\Exception $exception) {
            return response(['code'=>500,'message'=>$exception->getMessage()]);
        }
    }
}
