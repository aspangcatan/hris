<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
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
        return view('admin.permissions');
    }

    public function users(Request $request)
    {
        $search = $request->input('search', '');

        $query = DB::table('tdh_user.users as u')
            ->leftJoin('tdh_user.user_priv as up', 'up.user_id', '=', 'u.id')
            ->select(
                'u.id',
                'u.fname',
                'u.lname',
                'u.mname',
                DB::raw("COALESCE(up.level, 'standard') as level")
            )
            ->where('up.syscode','hris')
            ->where(function ($q) use ($search) {
                if ($search) {
                    $q->where('u.fname', 'like', "%{$search}%")
                        ->orWhere('u.lname', 'like', "%{$search}%");
                }
            })
            ->orderBy('u.lname')
            ->orderBy('u.fname');

        return response()->json($query->paginate(10));
    }

    public function show(int $id)
    {
        $user = DB::table('tdh_user.users as u')
            ->leftJoin('tdh_user.user_priv as up', function ($join) {
                $join->on('up.user_id', '=', 'u.id')
                    ->where('up.syscode', '=', 'hris');
            })
            ->where('u.id', $id)
            ->select(
                'u.id',
                'u.fname',
                'u.lname',
                DB::raw("COALESCE(up.level, 'standard') as level")
            )
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $permissions = $this->permissions->getForAdminUI($id);

        $auditLog = DB::table('hris.permission_audit_logs as pal')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'pal.performer_id')
            ->where('pal.target_user_id', $id)
            ->select(
                'pal.action',
                'pal.created_at',
                DB::raw("CONCAT(u.fname, ' ', u.lname) as performed_by")
            )
            ->orderByDesc('pal.created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'user' => $user,
            'permissions' => $permissions,
            'audit_log' => $auditLog,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'code' => 'required|string',
            'type' => 'required|in:grant,revoke,reset',
        ]);

        $code = $request->input('code');
        $type = $request->input('type');

        if ($type === 'reset') {
            $this->permissions->removeUserPermission(Auth::id(), $id, $code);
        } else {
            $this->permissions->setUserPermission(Auth::id(), $id, $code, $type);
        }

        $past = ['grant' => 'granted', 'revoke' => 'revoked', 'reset' => 'reset'];
        return response()->json(['message' => "Permission {$past[$type]} successfully."]);
    }

    public function reset(int $id)
    {
        $this->permissions->resetUserPermissions(Auth::id(), $id);
        return response()->json(['message' => 'All permissions reset to level defaults.']);
    }
}
