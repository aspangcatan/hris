<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\UserPrivelege;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function authenticate(Request $request)
    {
        $credentials = ['username' => $request->username, 'password' => $request->password];

        if (Auth::attempt($credentials)) {
            $user_priv = UserPrivelege::where([['user_id', Auth::id()], ['syscode', 'hris']])->get()->first();
            if ($user_priv == null) {
                $user_priv = new UserPrivelege;
                $user_priv->user_id = Auth::id();
                $user_priv->syscode = "hris";
                $user_priv->level = "standard";
                $user_priv->save();

                $employee = Employee::where('user_id',Auth::id())->first();
                if(!$employee){
                    $employee = new Employee();
                    $employee->user_id = Auth::id();
                    $employee->save();
                }
            }
            $designation = Designation::find(Auth::user()->designation);
            if (!$designation)
                session(['designation' => "N/A"]);
            else
                session(['designation' => $designation->description]);
            session(['access_rights' => $user_priv->level]);
            return redirect()->intended('/profile');
        }
        return back()->with(['message' => 'The provided credentials do not match our records.']);
    }
}
