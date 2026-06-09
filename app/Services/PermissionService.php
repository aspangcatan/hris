<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PermissionService
{
    private array $cache = [];

    public function can(int $userId, string $code): bool
    {
        return in_array($code, $this->effectiveCodes($userId), true);
    }

    public function getEffectivePermissions(int $userId): array
    {
        return $this->effectiveCodes($userId);
    }

    public function getForAdminUI(int $userId): array
    {
        $level = $this->getUserLevel($userId);

        $levelIds = DB::table('hris.access_level_permissions')
            ->where('level', $level)
            ->pluck('permission_id')
            ->flip()
            ->toArray();

        $overrides = DB::table('hris.user_permissions')
            ->where('user_id', $userId)
            ->pluck('type', 'permission_id')
            ->toArray();

        return DB::table('hris.permissions')
            ->orderBy('module')
            ->orderBy('code')
            ->get()
            ->map(function ($p) use ($levelIds, $overrides) {
                $fromLevel = array_key_exists($p->id, $levelIds);
                $granted   = ($overrides[$p->id] ?? null) === 'grant';
                $revoked   = ($overrides[$p->id] ?? null) === 'revoke';
                return [
                    'id'          => $p->id,
                    'module'      => $p->module,
                    'code'        => $p->code,
                    'description' => $p->description,
                    'from_level'  => $fromLevel,
                    'granted'     => $granted,
                    'revoked'     => $revoked,
                    'effective'   => ($fromLevel || $granted) && !$revoked,
                ];
            })
            ->groupBy('module')
            ->toArray();
    }

    public function setUserPermission(int $adminId, int $targetUserId, string $code, string $type): void
    {
        $permId = DB::table('hris.permissions')->where('code', $code)->value('id');
        if (!$permId) {
            throw new \InvalidArgumentException("Unknown permission code: {$code}");
        }

        DB::table('hris.user_permissions')->updateOrInsert(
            ['user_id' => $targetUserId, 'permission_id' => $permId],
            ['user_id' => $targetUserId, 'permission_id' => $permId, 'type' => $type,
             'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('hris.permission_audit_logs')->insert([
            'performer_id'   => $adminId,
            'target_user_id' => $targetUserId,
            'action'         => "{$type}d {$code}",
            'details'        => json_encode(['code' => $code, 'type' => $type]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        unset($this->cache[$targetUserId]);
    }

    public function removeUserPermission(int $adminId, int $targetUserId, string $code): void
    {
        $permId = DB::table('hris.permissions')->where('code', $code)->value('id');
        if (!$permId) return;

        DB::table('hris.user_permissions')
            ->where('user_id', $targetUserId)
            ->where('permission_id', $permId)
            ->delete();

        DB::table('hris.permission_audit_logs')->insert([
            'performer_id'   => $adminId,
            'target_user_id' => $targetUserId,
            'action'         => "reset {$code} to level default",
            'details'        => json_encode(['code' => $code, 'type' => 'reset']),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        unset($this->cache[$targetUserId]);
    }

    public function resetUserPermissions(int $adminId, int $targetUserId): void
    {
        $removedCodes = DB::table('hris.user_permissions as up')
            ->join('hris.permissions as p', 'p.id', '=', 'up.permission_id')
            ->where('up.user_id', $targetUserId)
            ->pluck('p.code')
            ->toArray();

        DB::table('hris.user_permissions')->where('user_id', $targetUserId)->delete();

        DB::table('hris.permission_audit_logs')->insert([
            'performer_id'   => $adminId,
            'target_user_id' => $targetUserId,
            'action'         => 'reset all permissions to level defaults',
            'details'        => json_encode(['removed' => $removedCodes]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        unset($this->cache[$targetUserId]);
    }

    private function effectiveCodes(int $userId): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        $level = $this->getUserLevel($userId);

        $levelIds = DB::table('hris.access_level_permissions')
            ->where('level', $level)
            ->pluck('permission_id');

        $grantIds = DB::table('hris.user_permissions')
            ->where('user_id', $userId)->where('type', 'grant')
            ->pluck('permission_id');

        $revokeFlip = DB::table('hris.user_permissions')
            ->where('user_id', $userId)->where('type', 'revoke')
            ->pluck('permission_id')
            ->flip()
            ->toArray();

        $effectiveIds = $levelIds->merge($grantIds)
            ->unique()
            ->filter(fn($id) => !array_key_exists($id, $revokeFlip))
            ->values();

        $this->cache[$userId] = DB::table('hris.permissions')
            ->whereIn('id', $effectiveIds)
            ->pluck('code')
            ->toArray();

        return $this->cache[$userId];
    }

    public function getUserLevel(int $userId): string
    {
        return strtolower(
            DB::table('tdh_user.user_priv')
                ->where('user_id', $userId)
                ->value('level') ?? 'standard'
        );
    }
}
