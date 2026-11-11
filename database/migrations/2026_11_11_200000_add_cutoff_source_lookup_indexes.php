<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lookup indexes for the per-employee reads done when a cutoff is generated
 * (audit A17). Indexes only: no data is changed. Safe to re-run.
 */
class AddCutoffSourceLookupIndexes extends Migration
{
    private const INDEXES = [
        'dtr_logs' => ['dtr_logs_userid_created_at_index', ['userid', 'created_at']],
        'schedules' => ['schedules_employee_date_index', ['employee_id', 'schedule_date']],
        'leave_date_applied' => ['leave_date_applied_app_date_index', ['leave_application_id', 'date_applied']],
    ];

    public function up()
    {
        foreach (self::INDEXES as $table => [$name, $columns]) {
            if (!$this->indexExists($table, $name)) {
                DB::statement(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)', $table, $name, implode('`, `', $columns)));
            }
        }
    }

    public function down()
    {
        foreach (self::INDEXES as $table => [$name]) {
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
