<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Access to the Reorientation Answers page (check post tests per module, bulk
 * pass/fail). Given to the HR level, and to admin, which holds every permission.
 * Data only - no table changes. Skips anything that already exists, so it never
 * undoes changes made on the Permissions page.
 */
return new class extends Migration
{
    private const PERMISSION = [
        'module' => 'reorientation',
        'code' => 'reorientation.answers',
        'description' => 'Open the Reorientation Answers page and mark post tests passed or failed',
    ];

    private const LEVELS = ['admin', 'hr'];

    public function up()
    {
        DB::transaction(function () {
            $id = DB::table('hris.permissions')->where('code', self::PERMISSION['code'])->value('id');
            if (!$id) {
                $id = DB::table('hris.permissions')->insertGetId(array_merge(self::PERMISSION, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            foreach (self::LEVELS as $level) {
                $exists = DB::table('hris.access_level_permissions')
                    ->where('level', $level)->where('permission_id', $id)->exists();
                if (!$exists) {
                    DB::table('hris.access_level_permissions')->insert([
                        'level' => $level,
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
            $id = DB::table('hris.permissions')->where('code', self::PERMISSION['code'])->value('id');
            if (!$id) {
                return;
            }

            DB::table('hris.user_permissions')->where('permission_id', $id)->delete();
            DB::table('hris.access_level_permissions')->where('permission_id', $id)->delete();
            DB::table('hris.permissions')->where('id', $id)->delete();
        });
    }
};
