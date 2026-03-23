<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!$this->columnExists('leave_types', 'status')) {
            Schema::table('hris.leave_types', function (Blueprint $table) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('description');
            });
        }

        if (!$this->columnExists('leave_types', 'monthly_earned')) {
            Schema::table('hris.leave_types', function (Blueprint $table) {
                $table->boolean('monthly_earned')->default(true)->after('status');
            });
        }
    }

    public function down()
    {
        if ($this->columnExists('leave_types', 'monthly_earned')) {
            Schema::table('hris.leave_types', function (Blueprint $table) {
                $table->dropColumn('monthly_earned');
            });
        }
        if ($this->columnExists('leave_types', 'status')) {
            Schema::table('hris.leave_types', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            ['hris', $table, $column]
        );

        return $result && (int) $result->count > 0;
    }
};
