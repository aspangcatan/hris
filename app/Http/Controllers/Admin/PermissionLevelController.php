<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionLevelController extends Controller
{
    private const EDITABLE_LEVELS = ['dh', 'hr', 'schedule', 'sh', 'standard'];

    public function __construct(private PermissionService $permissions)
    {
        $this->middleware(function ($request, $next) {
            if (strtolower((string) session('access_rights')) !== 'admin') {
                return $request->expectsJson()
                    ? response()->json(['error' => 'Unauthorized'], 403)
                    : abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        return view('admin.permission_levels');
    }

    public function data()
    {
        return response()->json($this->permissions->getLevelPermissionMatrix());
    }

    public function update(Request $request)
    {
        $request->validate([
            'level'   => 'required|string',
            'code'    => 'required|string',
            'granted' => 'required|boolean',
        ]);

        $level = strtolower($request->input('level'));

        if ($level === 'admin') {
            return response()->json(['message' => 'Cannot modify admin level.'], 422);
        }

        if (!in_array($level, self::EDITABLE_LEVELS, true)) {
            return response()->json(['message' => 'Invalid level.'], 422);
        }

        $code = $request->input('code');
        if (!DB::table('hris.permissions')->where('code', $code)->exists()) {
            return response()->json(['message' => 'Unknown permission code.'], 422);
        }

        $this->permissions->setLevelPermission($level, $code, (bool) $request->input('granted'));

        return response()->json(['message' => 'Updated.']);
    }
}
