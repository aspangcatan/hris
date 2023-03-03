<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    //

    public function getAddress(Request $request)
    {
        try {
            $addresses = Address::where('user_id', Auth::id())->get();
            return response(['code' => 200, 'results' => $addresses]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateAddress(Request $request)
    {
        try {
            $address_array = $request->address;
            for($i=0; $i<count($address_array); $i++){
                $address = Address::where([['user_id', Auth::id()], ['address_type', $address_array[$i]['address_type']]])->first();
                if (!$address) {
                    $address = new Address;
                    $address->user_id = Auth::id();
                    $address->address_type = $address_array[$i]['address_type'];
                }

                $address->house_no = $address_array[$i]['house_no'];
                $address->street = $address_array[$i]['street'];
                $address->subdivision_village = $address_array[$i]['subdivision_village'];
                $address->barangay = $address_array[$i]['barangay'];
                $address->city = $address_array[$i]['city'];
                $address->province = $address_array[$i]['province'];
                $address->zip_code = $address_array[$i]['zipcode'];
                $address->save();
            }
            return response(['code' => 200, 'message' => 'Address updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
