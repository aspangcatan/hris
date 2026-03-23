<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!$this->columnExists('payrolls', 'cutoff_start_date')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->date('cutoff_start_date')->nullable()->after('end_date');
            });
        }

        if (!$this->columnExists('payrolls', 'cutoff_end_date')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->date('cutoff_end_date')->nullable()->after('cutoff_start_date');
            });
        }

        if (!$this->columnExists('payrolls', 'cutoff_title')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->string('cutoff_title', 255)->nullable()->after('cutoff_end_date');
            });
        }
    }

    public function down()
    {
        if ($this->columnExists('payrolls', 'cutoff_title')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->dropColumn('cutoff_title');
            });
        }
        if ($this->columnExists('payrolls', 'cutoff_end_date')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->dropColumn('cutoff_end_date');
            });
        }
        if ($this->columnExists('payrolls', 'cutoff_start_date')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->dropColumn('cutoff_start_date');
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
