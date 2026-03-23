<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!$this->tableExists('payroll_tax_rules')) {
            Schema::create('hris.payroll_tax_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('purpose', 150)->index();
                $table->decimal('min_annual', 12)->default(0);
                $table->decimal('max_annual', 12)->nullable();
                $table->decimal('rate_percent', 5, 2)->default(0);
                $table->enum('tax_mode', ['flat', 'progressive'])->default('flat');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('hris.payroll_tax_rules');
    }

    private function tableExists(string $table): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            ['hris', $table]
        );

        return $result && (int) $result->count > 0;
    }
};
