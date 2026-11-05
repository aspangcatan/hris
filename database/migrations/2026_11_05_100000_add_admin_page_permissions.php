<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission codes for pages that used to be gated on the session role only:
 * Schedule Access, Calendar, Hospital Order Create and Position Applied.
 *
 * Data only - no table changes. Adds each code and its default levels, skipping
 * anything that already exists, so it never undoes changes made on the
 * Permission Levels page and is safe to run on a database that already has them.
 *
 * Run on its own:
 *   php artisan migrate --path=database/migrations/2026_11_05_100000_add_admin_page_permissions.php
 */
class AddAdminPagePermissions extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'schedule',       'code' => 'schedule.access',       'description' => 'Assign which sections each schedule manager handles', 'levels' => ['admin']],
        ['module' => 'calendar',       'code' => 'calendar.manage',       'description' => 'Add/edit/delete holidays in the calendar',            'levels' => ['admin']],
        ['module' => 'hospital_order', 'code' => 'hospital_order.create', 'description' => 'Create hospital orders',                              'levels' => ['admin']],
        ['module' => 'promotion',      'code' => 'promotion.manage',      'description' => 'Record positions applied for promotion',              'levels' => ['admin', 'hr', 'hrh']],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::PERMISSIONS as $p) {
                $id = DB::table('hris.permissions')->where('code', $p['code'])->value('id');

                if (!$id) {
                    $id = DB::table('hris.permissions')->insertGetId([
                        'module'      => $p['module'],
                        'code'        => $p['code'],
                        'description' => $p['description'],
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                foreach ($p['levels'] as $level) {
                    $exists = DB::table('hris.access_level_permissions')
                        ->where('level', $level)
                        ->where('permission_id', $id)
                        ->exists();

                    if (!$exists) {
                        DB::table('hris.access_level_permissions')->insert([
                            'level'         => $level,
                            'permission_id' => $id,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Removes the four codes together with every level default and per-employee
     * grant/revoke that refers to them.
     */
    public function down(): void
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
}
