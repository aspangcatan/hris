<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Named pay computations, set per shift. Daily Base = basic / working days;
 * Hourly = Daily Base / standard hours; Minute = Hourly / 60. A shift with no
 * computation uses the default. Each cutoff saves what it used, so editing a
 * computation never changes a finalized cutoff.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('pay_computations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('name', 100)->unique();
            $table->decimal('working_days_per_month', 5, 2);
            $table->decimal('standard_hours_per_day', 5, 2);
            $table->boolean('is_default')->default(false);
            $table->text('remarks')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('pay_computations')->insert([
            'name' => '40 hours per week computation',
            'working_days_per_month' => 22,
            'standard_hours_per_day' => 8,
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('schedule_master_lists', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_computation_id')->nullable();
            $table->foreign('pay_computation_id', 'shift_pay_computation_fk')->references('id')->on('pay_computations');
        });

        Schema::table('cutoff_summary_days', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_computation_id')->nullable();
            $table->decimal('minute_rate', 16, 8)->nullable();
        });

        Schema::table('cutoff_employee_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_computation_id')->nullable();
            $table->string('pay_computation_name', 100)->nullable();
        });
    }

    public function down()
    {
        Schema::table('cutoff_employee_snapshots', function (Blueprint $table) {
            $table->dropColumn(['pay_computation_id', 'pay_computation_name']);
        });
        Schema::table('cutoff_summary_days', function (Blueprint $table) {
            $table->dropColumn(['pay_computation_id', 'minute_rate']);
        });
        Schema::table('schedule_master_lists', function (Blueprint $table) {
            $table->dropForeign('shift_pay_computation_fk');
            $table->dropColumn('pay_computation_id');
        });
        Schema::dropIfExists('pay_computations');
    }
};
