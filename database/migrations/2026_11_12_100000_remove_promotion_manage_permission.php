<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Position Applied is now open to every logged-in user (each user only sees
 * their own applications), so the promotion.manage permission no longer gates
 * anything. Remove it so it doesn't show up as a dead toggle on the
 * Permissions / Permission Levels pages. Data only - no table changes.
 */
return new class extends Migration
{
    public function up()
    {
        $id = DB::table('hris.permissions')->where('code', 'promotion.manage')->value('id');
        if (!$id) {
            return;
        }

        DB::table('hris.access_level_permissions')->where('permission_id', $id)->delete();
        DB::table('hris.user_permissions')->where('permission_id', $id)->delete();
        DB::table('hris.permissions')->where('id', $id)->delete();
    }

    public function down()
    {
        $id = DB::table('hris.permissions')->insertGetId([
            'module' => 'promotion',
            'code' => 'promotion.manage',
            'description' => 'Record positions applied for promotion',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['admin', 'hr', 'hrh'] as $level) {
            DB::table('hris.access_level_permissions')->insert([
                'level' => $level,
                'permission_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
