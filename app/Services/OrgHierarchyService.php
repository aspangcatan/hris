<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrgHierarchyService
{
    public function getManagedEmployeeIds(int $headUserId): array
    {
        $managedDivisionIds = DB::table('tdh_user.division')
            ->where('head', $headUserId)
            ->pluck('id')
            ->all();

        if (!empty($managedDivisionIds)) {
            return DB::table('tdh_user.users')
                ->whereIn('division', $managedDivisionIds)
                ->where('id', '!=', $headUserId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $managedSectionIds = DB::table('tdh_user.section')
            ->where('head', $headUserId)
            ->pluck('id')
            ->all();

        if (!empty($managedSectionIds)) {
            return DB::table('tdh_user.users')
                ->whereIn('section', $managedSectionIds)
                ->where('id', '!=', $headUserId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    public function getManagedEmployees(int $headUserId)
    {
        $managedIds = $this->getManagedEmployeeIds($headUserId);

        if (empty($managedIds)) {
            return collect();
        }

        return DB::table('tdh_user.users')
            ->whereIn('id', $managedIds)
            ->orderBy('lname')
            ->orderBy('fname')
            ->get([
                'id',
                'fname',
                'lname',
                DB::raw("CONCAT(COALESCE(lname, ''), ', ', COALESCE(fname, '')) as full_name"),
            ]);
    }
}
