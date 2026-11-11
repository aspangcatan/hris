<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unique keys so duplicates are refused by the database, not only by code
 * (audit A17). All tables are checked first: if any has duplicates, nothing is
 * changed and the migration stops listing them, for HR to resolve. No row is
 * ever merged or deleted here. Safe to re-run.
 */
class AddUniqueKeysToCreditAndPayrollTables extends Migration
{
    private const KEYS = [
        'leave_credits' => ['leave_credits_user_leave_unique', ['user_id', 'leave_id']],
        'credit_earned_logs' => ['credit_earned_logs_user_period_type_unique', ['user_id', 'year', 'month', 'leave_type_id']],
        'payroll_employees' => ['payroll_employees_payroll_employee_unique', ['payroll_id', 'employee_id']],
    ];

    public function up()
    {
        $problems = [];
        foreach (self::KEYS as $table => [$name, $columns]) {
            if ($this->indexExists($table, $name)) {
                continue;
            }
            $duplicates = DB::table($table)
                ->select($columns)
                ->selectRaw('COUNT(*) AS copies')
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->limit(20)
                ->get();
            if ($duplicates->isNotEmpty()) {
                $problems[] = $table . ': ' . $duplicates->toJson();
            }
        }
        if (!empty($problems)) {
            throw new \RuntimeException("Duplicates found, no key was added (first 20 per table):\n" . implode("\n", $problems));
        }

        foreach (self::KEYS as $table => [$name, $columns]) {
            if (!$this->indexExists($table, $name)) {
                DB::statement(sprintf('ALTER TABLE `%s` ADD UNIQUE KEY `%s` (`%s`)', $table, $name, implode('`, `', $columns)));
            }
        }
    }

    public function down()
    {
        foreach (self::KEYS as $table => [$name]) {
            if ($this->indexExists($table, $name)) {
                DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $name));
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
}
