<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $table = $this->resolveCutoffTable();

        if (!$this->columnExists($table, 'is_locked')) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('is_locked')->default(false)->after('remarks');
            });
        }
        if (!$this->columnExists($table, 'locked_by')) {
            Schema::table($table, function (Blueprint $table) {
                $table->integer('locked_by')->nullable()->after('is_locked');
            });
        }
        if (!$this->columnExists($table, 'locked_at')) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('locked_at')->nullable()->after('locked_by');
            });
        }
    }

    public function down()
    {
        $table = $this->resolveCutoffTable();

        if ($this->columnExists($table, 'locked_at')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('locked_at');
            });
        }
        if ($this->columnExists($table, 'locked_by')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('locked_by');
            });
        }
        if ($this->columnExists($table, 'is_locked')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('is_locked');
            });
        }
    }

    private function resolveCutoffTable(): string
    {
        if ($this->tableExists('hris', 'cutoff_summaries')) {
            return 'hris.cutoff_summaries';
        }

        return 'cutoff_summaries';
    }

    private function tableExists(string $schema, string $table): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$schema, $table]
        );

        return $result && (int) $result->count > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = str_contains($table, '.') ? explode('.', $table)[0] : env('DB_DATABASE');
        $tableName = str_contains($table, '.') ? explode('.', $table)[1] : $table;

        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$schema, $tableName, $column]
        );

        return $result && (int) $result->count > 0;
    }
};
