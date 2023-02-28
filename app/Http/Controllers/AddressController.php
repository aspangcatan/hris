<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AddressController extends Controller
{
    //

    public function getAddress(Request $request)
    {
        try {

        } catch (\Exception $exception){
            return response(['code'=>500,'message'=>$exception->getMessage()])
        }
    }
}
