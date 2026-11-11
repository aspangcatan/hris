<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effective-dated pay. Past salaries were never recorded, so each employee
 * starts with one row copied from payroll_settings, marked source = 'go_live'.
 * effective_from NULL on that row means "the value on file before history
 * began"; it is not evidence of what was paid on any earlier date.
 * payroll_settings is only read here, never changed.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('employee_salary_history', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->integer('salary_grade');
            $table->integer('step');
            $table->decimal('basic_salary', 12, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('source', 30);
            $table->integer('changed_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['userid', 'effective_from'], 'salary_history_user_from_unique');
            $table->index(['userid', 'effective_to'], 'salary_history_user_to_index');
        });

        // Latest row per employee, in case payroll_settings has duplicates.
        DB::statement("
            INSERT INTO employee_salary_history
                (userid, salary_grade, step, basic_salary, effective_from, effective_to, source, created_at, updated_at)
            SELECT ps.userid, ps.salary_grade, ps.step, ps.basic_salary, NULL, NULL, 'go_live', NOW(), NOW()
            FROM payroll_settings ps
            JOIN (SELECT userid, MAX(id) AS id FROM payroll_settings GROUP BY userid) latest ON latest.id = ps.id
        ");
    }

    public function down()
    {
        Schema::dropIfExists('employee_salary_history');
    }
};
