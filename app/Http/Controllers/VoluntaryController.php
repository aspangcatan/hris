<?php

namespace App\Http\Controllers;

use App\Models\Voluntary;
use Illuminate\Http\Request;

class VoluntaryController extends Controller
{
    //
    public function getVoluntary()
    {
        try {
            
            $voluntary = Voluntary::where('user_id', Auth::id())->get();
            return response(['code' => 200, 'results' => $voluntary]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateVoluntary(Request $request)
    {
        try {
            
            $voluntary = Voluntary::find($request->id);
            if (!$voluntary) {
                $voluntary = new Voluntary;
                $voluntary->user_id = Auth::id();
            }
            $voluntary->organization_address = $request->organization_address;
            $voluntary->working_from = $request->working_from;
            $voluntary->working_to = $request->working_to;
            $voluntary->number_hours = $request->number_hours;
            $voluntary->position = $request->position;
            $voluntary->save();
            return response(['code' => 200, 'message' => 'Voluntary work updated','id'=>$voluntary->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteVoluntary(Request $request)
    {
        try {
            $voluntary = Voluntary::find($request->id);
            if (!$voluntary) {
                return response(['code' => 500, 'message' => 'Voluntary ID dont exists']);
            }
            $voluntary->forceDelete();
            return response(['code' => 200, 'message' => 'Voluntary work deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
