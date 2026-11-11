<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $code): mixed
    {
        if (!Auth::check()) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Unauthenticated'], 401)
                : redirect()->route('login');
        }

        // "a|b" passes with either permission.
        $allowed = false;
        foreach (explode('|', $code) as $candidate) {
            $allowed = $allowed || $this->permissions->can(Auth::id(), $candidate);
        }

        if (!$allowed) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Unauthorized'], 403)
                : redirect()->route('profile')->with('error', 'You do not have permission to access that page.');
        }

        return $next($request);
    }
}
