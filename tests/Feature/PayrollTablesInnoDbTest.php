<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payroll tables must be InnoDB: on MyISAM, DB::transaction() in payroll
 * computation and DatabaseTransactions in tests are silent no-ops, and test
 * runs left the live tax rules and components deactivated (2026-11-23).
 */
class PayrollTablesInnoDbTest extends TestCase
{
    public const TABLES = [
        'employee_payroll_components', 'payroll_approvals', 'payroll_assignatories', 'payroll_compensation_types',
        'payroll_components', 'payroll_employee_compensations', 'payroll_employee_components', 'payroll_employee_summaries',
        'payroll_employees', 'payroll_periods', 'payroll_settings', 'payroll_tax_rules', 'payrolls',
    ];

    /** @test */
    public function every_payroll_table_is_innodb()
    {
        $engines = collect(DB::select(
            "SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.tables WHERE table_schema = 'hris' AND table_name IN ("
            . implode(',', array_fill(0, count(self::TABLES), '?')) . ')',
            self::TABLES
        ))->pluck('engine', 'name');

        foreach (self::TABLES as $table) {
            $this->assertSame('InnoDB', $engines[$table] ?? null, "hris.{$table} is not InnoDB.");
        }
    }
}
