<?php

namespace App\Http\Controllers;

use App\Models\Reference;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function getReference(Request $request)
    {
        try {
            
            $references = Reference::where('user_id', Auth::id())->get();
            return response(['code' => 200, 'results' => $references]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateReference(Request $request)
    {
        try {
            
            $reference = Reference::find($request->id);
            if (!$reference) {
                $reference = new Reference;
                $reference->user_id = Auth::id();
            }
            $reference->name = $request->name;
            $reference->address = $request->address;
            $reference->contact_no = $request->contact_no;
            $reference->save();
            return response(['code' => 200, 'message' => 'References updated', 'id' => $reference->id]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteReference(Request $request)
    {
        try {
            $reference = Reference::find($request->id);
            if (!$reference) {
                return response(['code' => 500, 'message' => 'Reference ID dont exists']);
            }
            $reference->forceDelete();
            return response(['code' => 200, 'message' => 'Reference deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
