<?php

namespace App\Http\Controllers;

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
            }

            session(['access_rights' => $user_priv->level]);
            return redirect()->intended('/profile');
        }
        return back()->with(['message' => 'The provided credentials do not match our records.']);
    }
}
