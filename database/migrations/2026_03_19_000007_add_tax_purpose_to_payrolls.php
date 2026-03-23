<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!$this->columnExists('payrolls', 'tax_purpose')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->string('tax_purpose', 150)->nullable()->after('remarks');
            });
        }
    }

    public function down()
    {
        if ($this->columnExists('payrolls', 'tax_purpose')) {
            Schema::table('hris.payrolls', function (Blueprint $table) {
                $table->dropColumn('tax_purpose');
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
