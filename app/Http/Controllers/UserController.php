<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserPrivelege;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            }

            $employee = Employee::where('user_id', Auth::id())->first();
            if (!$employee) {
                $employee = new Employee();
                $employee->user_id = Auth::id();
                $employee->save();
            }

            $designation = Designation::find(Auth::user()->designation);
            if (!$designation)
                session(['designation' => "N/A"]);
            else
                session(['designation' => $designation->description]);

            session(['access_rights' => $user_priv->level]);
            $isSectionHead = DB::table('tdh_user.section')->where('head', Auth::id())->exists();
            $isDivisionHead = DB::table('tdh_user.division')->where('head', Auth::id())->exists();
            session([
                'is_section_head' => $isSectionHead,
                'is_division_head' => $isDivisionHead,
            ]);
            
            app(PermissionService::class)->syncHeadshipGrants(Auth::id(), $isSectionHead, $isDivisionHead);

            return redirect()->intended('/profile');
        }
        return back()->with(['message' => 'The provided credentials do not match our records.']);
    }

    public function changePassword(Request $request)
    {
        try {
            $user = User::find(Auth::id());
            if (!Hash::check($request->old_password, $user->password)) {
                return ['code' => 500, 'message' => 'Incorrect old password'];
            }

            $user->password = bcrypt($request->new_password);
            $user->save();
            return ['code' => 200, 'message' => 'Password successfully changed'];
        } catch (\Exception $exception) {
            return ['code' => 500, 'message' => $exception->getMessage()];
        }
    }
}
