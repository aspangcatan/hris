<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!$this->tableExists('payroll_components')) {
            Schema::create('hris.payroll_components', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 100)->unique();
                $table->string('name');
                $table->enum('type', ['earning', 'deduction'])->index();
                $table->boolean('is_global')->default(false);
                $table->boolean('taxable')->default(false);
                $table->decimal('default_amount', 12)->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!$this->tableExists('employee_payroll_components')) {
            Schema::create('hris.employee_payroll_components', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payroll_component_id')->index();
                $table->integer('userid')->index();
                $table->decimal('amount', 12)->default(0);
                $table->decimal('amount_override', 12)->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_excluded')->default(false);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['payroll_component_id', 'userid'], 'employee_component_unique');
            });
        }

        if (!$this->tableExists('payroll_employee_components')) {
            Schema::create('hris.payroll_employee_components', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payroll_employee_id')->index();
                $table->unsignedBigInteger('payroll_component_id')->index();
                $table->string('code', 100);
                $table->string('name');
                $table->enum('type', ['earning', 'deduction']);
                $table->boolean('taxable')->default(false);
                $table->decimal('amount', 12)->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hris.payroll_employee_components');
        Schema::dropIfExists('hris.employee_payroll_components');
        Schema::dropIfExists('hris.payroll_components');
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
