<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollTestSeeder extends Seeder
{
    public function run(): void
    {
        $user = DB::table('tdh_user.users')
            ->where('username', 'apangcatan')
            ->first(['id']);

        if (!$user) {
            $this->command->error('User apangcatan not found in tdh_user.users.');
            return;
        }

        $userId = $user->id;
        $basicSalary = 40000.00;

        // Payroll settings
        DB::table('hris.payroll_settings')->updateOrInsert(
            ['userid' => $userId],
            [
                'salary_grade' => 18,
                'step'         => 1,
                'basic_salary' => $basicSalary,
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        // Component definitions: [code, name, type, taxable, default_amount, amount]
        $components = [
            ['GSIS_PS', 'GSIS Personal Share',  'deduction', false, 0,       round($basicSalary * 0.09, 2)],
            ['PHIC',    'PhilHealth',            'deduction', false, 0,       round($basicSalary * 0.05, 2)],
            ['HDMF',    'Pag-IBIG',              'deduction', false, 100.00,  100.00],
            ['PERA',    'PERA',                  'earning',   true,  2000.00, 2000.00],
            ['RATA',    'RATA',                  'earning',   false, 3000.00, 3000.00],
        ];

        foreach ($components as [$code, $name, $type, $taxable, $defaultAmount, $amount]) {
            // Get or create the component master record
            $componentId = DB::table('hris.payroll_components')
                ->where('code', $code)
                ->value('id');

            if (!$componentId) {
                $componentId = DB::table('hris.payroll_components')->insertGetId([
                    'code'           => $code,
                    'name'           => $name,
                    'type'           => $type,
                    'taxable'        => $taxable ? 1 : 0,
                    'is_global'      => 1,
                    'default_amount' => $defaultAmount,
                    'is_active'      => 1,
                    'sort_order'     => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // Upsert employee component
            DB::table('hris.employee_payroll_components')->updateOrInsert(
                [
                    'payroll_component_id' => $componentId,
                    'userid'               => $userId,
                ],
                [
                    'amount'       => $amount,
                    'is_active'    => 1,
                    'is_excluded'  => 0,
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
        }

        $this->command->info("Seeded payroll settings and components for user apangcatan (id: {$userId}).");
    }
}
