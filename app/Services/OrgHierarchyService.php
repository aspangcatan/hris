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

    /** Code of the top division; its head is the officer above every Division Head. */
    public const TOP_DIVISION_CODE = 'MCC';

    /** @return int[] ids of the sections this user actually heads (tdh_user.section.head). */
    public function headedSectionIds(int $userId): array
    {
        return DB::table('tdh_user.section')->where('head', $userId)->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    /** @return int[] ids of the divisions this user actually heads (tdh_user.division.head). */
    public function headedDivisionIds(int $userId): array
    {
        return DB::table('tdh_user.division')->where('head', $userId)->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    public function topDivisionId(): ?int
    {
        $id = DB::table('tdh_user.division')->where('code', self::TOP_DIVISION_CODE)->value('id');
        return $id ? (int) $id : null;
    }

    /**
     * SH/DH steps of an approval chain for something $user files (HR is added by the caller).
     * Nobody approves their own filing; their step goes to the head above them:
     *  - regular employee          -> SH (own section)  -> DH (own division)
     *  - head of own section       -> DH (own division)
     *  - head of own division      -> DH step approved by the Medical Center Chief (top division)
     *  - the Medical Center Chief  -> no SH/DH step (HR only)
     *
     * @return array<int, array{role: string, approver_id: int|null}>
     */
    public function approvalChainFor(object $user): array
    {
        $userId     = (int) $user->id;
        $sectionId  = $user->section ? (int) $user->section : null;
        $divisionId = $user->division ? (int) $user->division : null;
        $topId      = $this->topDivisionId();

        $headsDivisions = $this->headedDivisionIds($userId);

        if ($topId !== null && in_array($topId, $headsDivisions, true)) {
            return [];
        }

        if ($divisionId !== null && in_array($divisionId, $headsDivisions, true)) {
            return [['role' => 'DH', 'approver_id' => $topId]];
        }

        if ($sectionId !== null && in_array($sectionId, $this->headedSectionIds($userId), true)) {
            return [['role' => 'DH', 'approver_id' => $divisionId]];
        }

        return [
            ['role' => 'SH', 'approver_id' => $sectionId],
            ['role' => 'DH', 'approver_id' => $divisionId],
        ];
    }

    /**
     * Rows for an approval table: the SH/DH chain for $user followed by HR, numbered 1..n.
     * $fk is the parent column (e.g. leave_application_id) and $parentId its value.
     */
    public function approvalRowsFor(object $user, string $fk, int $parentId, int $hrApproverId = 15): array
    {
        $steps   = $this->approvalChainFor($user);
        $steps[] = ['role' => 'HR', 'approver_id' => $hrApproverId];

        $rows = [];
        foreach ($steps as $i => $step) {
            $rows[] = [
                $fk           => $parentId,
                'approver_id' => $step['approver_id'],
                'role'        => $step['role'],
                'order_no'    => $i + 1,
                'status'      => 'pending',
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        return $rows;
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
