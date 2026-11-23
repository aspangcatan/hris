<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The payroll tables were MyISAM, so DB::transaction() around payroll
     * computation, approval and release was a silent no-op, and test
     * rollbacks did nothing (test runs left the live tax rules and
     * components deactivated). No column changes - engine only.
     */
    private const TABLES = [
        'employee_payroll_components', 'payroll_approvals', 'payroll_assignatories', 'payroll_compensation_types',
        'payroll_components', 'payroll_employee_compensations', 'payroll_employee_components', 'payroll_employee_summaries',
        'payroll_employees', 'payroll_periods', 'payroll_settings', 'payroll_tax_rules', 'payrolls',
    ];

    public function up()
    {
        foreach (self::TABLES as $table) {
            if ($this->engine($table) === 'MyISAM') {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table) {
            if ($this->engine($table) === 'InnoDB') {
                DB::statement("ALTER TABLE `{$table}` ENGINE=MyISAM");
            }
        }
    }

    private function engine(string $table): ?string
    {
        $row = DB::selectOne(
            'SELECT ENGINE AS engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );

        return $row->engine ?? null;
    }
};
