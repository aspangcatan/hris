<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['module' => 'dtr',            'code' => 'dtr.view_own',            'description' => 'View own DTR'],
            ['module' => 'dtr',            'code' => 'dtr.view_others',         'description' => "View other employees' DTR"],
            ['module' => 'dtr',            'code' => 'dtr.edit',                'description' => 'Edit/correct DTR entries'],
            ['module' => 'dtr',            'code' => 'dtr.print',               'description' => 'Print DTR'],
            ['module' => 'employee',       'code' => 'employee.view',           'description' => 'View employee profiles'],
            ['module' => 'employee',       'code' => 'employee.edit',           'description' => 'Edit employee PDS data'],
            ['module' => 'leave',          'code' => 'leave.file',              'description' => 'File leave applications'],
            ['module' => 'leave',          'code' => 'leave.approve',           'description' => 'Approve leave applications'],
            ['module' => 'leave',          'code' => 'leave.manage_credits',    'description' => 'Add/edit leave credits'],
            ['module' => 'leave',          'code' => 'leave.manage_types',      'description' => 'Manage leave types'],
            ['module' => 'payroll',        'code' => 'payroll.view',            'description' => 'View payroll records'],
            ['module' => 'payroll',        'code' => 'payroll.manage',          'description' => 'Create/edit payrolls'],
            ['module' => 'payroll',        'code' => 'payroll.settings',        'description' => 'Manage payroll settings'],
            ['module' => 'overtime',       'code' => 'overtime.file',           'description' => 'File overtime requests'],
            ['module' => 'overtime',       'code' => 'overtime.approve',        'description' => 'Approve overtime requests'],
            ['module' => 'passslip',       'code' => 'passslip.file',           'description' => 'File pass slips'],
            ['module' => 'passslip',       'code' => 'passslip.approve',        'description' => 'Approve pass slips'],
            ['module' => 'schedule',       'code' => 'schedule.view',           'description' => 'View schedules'],
            ['module' => 'schedule',       'code' => 'schedule.manage',         'description' => 'Manage schedules'],
            ['module' => 'saln',           'code' => 'saln.view_own',           'description' => 'View own SALN'],
            ['module' => 'saln',           'code' => 'saln.admin',              'description' => 'Administer all SALNs'],
            ['module' => 'hospital_order', 'code' => 'hospital_order.file',     'description' => 'File hospital orders'],
            ['module' => 'hospital_order', 'code' => 'hospital_order.approve',  'description' => 'Approve/manage hospital orders'],
            ['module' => 'reports',        'code' => 'reports.quarterly_leave', 'description' => 'Access quarterly leave balance reports'],
        ];

        foreach ($catalog as $p) {
            DB::table('hris.permissions')->updateOrInsert(
                ['code' => $p['code']],
                array_merge($p, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        $pid = fn(string $code) => DB::table('hris.permissions')->where('code', $code)->value('id');

        $levelDefaults = [
            'admin' => array_column($catalog, 'code'),
            'hr' => [
                'dtr.view_own','dtr.view_others','dtr.print',
                'employee.view','employee.edit',
                'leave.file','leave.approve','leave.manage_credits','leave.manage_types',
                'payroll.view','payroll.manage',
                'overtime.file','overtime.approve',
                'passslip.file','passslip.approve',
                'schedule.view',
                'saln.view_own','saln.admin',
                'hospital_order.file','hospital_order.approve',
                'reports.quarterly_leave',
            ],
            'hrh' => [
                'dtr.view_own','dtr.view_others','dtr.print',
                'employee.view','employee.edit',
                'leave.file','leave.approve','leave.manage_credits','leave.manage_types',
                'payroll.view','payroll.manage',
                'overtime.file','overtime.approve',
                'passslip.file','passslip.approve',
                'schedule.view',
                'saln.view_own','saln.admin',
                'hospital_order.file','hospital_order.approve',
                'reports.quarterly_leave',
            ],
            'dh' => [
                'dtr.view_own','dtr.view_others','dtr.print',
                'employee.view',
                'leave.file','leave.approve',
                'overtime.file','overtime.approve',
                'passslip.file','passslip.approve',
                'schedule.view',
                'saln.view_own',
                'hospital_order.file','hospital_order.approve',
                'payroll.view',
                'reports.quarterly_leave',
            ],
            'schedule' => [
                'dtr.view_own','dtr.view_others','dtr.edit','dtr.print',
                'schedule.view','schedule.manage',
            ],
            'it' => [
                'dtr.view_own','dtr.view_others','dtr.edit','dtr.print',
            ],
            'standard' => [
                'dtr.view_own','dtr.print',
                'employee.view',
                'leave.file',
                'overtime.file',
                'passslip.file',
                'saln.view_own',
                'schedule.view',
                'hospital_order.file',
            ],
        ];

        foreach ($levelDefaults as $level => $codes) {
            foreach ($codes as $code) {
                $permId = $pid($code);
                if (!$permId) continue;
                DB::table('hris.access_level_permissions')->updateOrInsert(
                    ['level' => $level, 'permission_id' => $permId],
                    ['level' => $level, 'permission_id' => $permId, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $approvalIds = array_filter(array_map($pid, [
            'leave.approve','overtime.approve','passslip.approve','hospital_order.approve',
        ]));

        $shIds = DB::table('tdh_user.users as u')
            ->join('tdh_user.section as s', 's.head', '=', 'u.id')
            ->pluck('u.id');
        $dhIds = DB::table('tdh_user.users as u')
            ->join('tdh_user.division as d', 'd.head', '=', 'u.id')
            ->pluck('u.id');
        $headIds = $shIds->merge($dhIds)->unique();

        foreach ($headIds as $userId) {
            foreach ($approvalIds as $permId) {
                DB::table('hris.user_permissions')->updateOrInsert(
                    ['user_id' => $userId, 'permission_id' => $permId],
                    ['user_id' => $userId, 'permission_id' => $permId, 'type' => 'grant',
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $this->command?->info('PermissionSeeder: ' . count($catalog) . ' permissions seeded, auto-granted to ' . $headIds->count() . ' heads.');
    }
}
