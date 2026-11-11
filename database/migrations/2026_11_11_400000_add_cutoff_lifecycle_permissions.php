<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Who may finalize a cutoff and who may reopen one: the HRH level only (client
 * decision 2026-11-11). Data only - no table changes. Skips anything that
 * already exists, so it never undoes changes made on the Permissions page.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['code' => 'cutoff.finalize', 'description' => 'Finalize a reviewed cutoff so payroll and earned credits can use it'],
        ['code' => 'cutoff.reopen', 'description' => 'Send a finalized cutoff back to draft, with a reason'],
    ];

    private const LEVEL = 'hrh';

    public function up()
    {
        DB::transaction(function () {
            foreach (self::PERMISSIONS as $permission) {
                $id = DB::table('hris.permissions')->where('code', $permission['code'])->value('id');
                if (!$id) {
                    $id = DB::table('hris.permissions')->insertGetId([
                        'module' => 'payroll',
                        'code' => $permission['code'],
                        'description' => $permission['description'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $exists = DB::table('hris.access_level_permissions')
                    ->where('level', self::LEVEL)->where('permission_id', $id)->exists();
                if (!$exists) {
                    DB::table('hris.access_level_permissions')->insert([
                        'level' => self::LEVEL,
                        'permission_id' => $id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down()
    {
        DB::transaction(function () {
            $ids = DB::table('hris.permissions')
                ->whereIn('code', array_column(self::PERMISSIONS, 'code'))
                ->pluck('id');

            DB::table('hris.user_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('hris.access_level_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('hris.permissions')->whereIn('id', $ids)->delete();
        });
    }
};
