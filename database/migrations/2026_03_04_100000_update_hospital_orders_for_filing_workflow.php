<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateHospitalOrdersForFilingWorkflow extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hospital_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('hospital_orders', 'order_number')) {
                $table->string('order_number', 100)->nullable()->after('id');
            }
            if (!Schema::hasColumn('hospital_orders', 'order_date')) {
                $table->date('order_date')->nullable()->after('order_number');
            }
            if (!Schema::hasColumn('hospital_orders', 'time_from')) {
                $table->time('time_from')->nullable()->after('order_date');
            }
            if (!Schema::hasColumn('hospital_orders', 'time_to')) {
                $table->time('time_to')->nullable()->after('time_from');
            }
        });

        if (Schema::hasColumn('hospital_orders', 'hospital_order')) {
            DB::table('hospital_orders')
                ->whereNull('order_number')
                ->update(['order_number' => DB::raw('hospital_order')]);
        }

        if (Schema::hasColumn('hospital_orders', 'start_date')) {
            DB::table('hospital_orders')
                ->whereNull('order_date')
                ->update(['order_date' => DB::raw('start_date')]);
        }

        Schema::table('hospital_order_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('hospital_order_employees', 'filed_date')) {
                $table->date('filed_date')->nullable()->after('userid');
            }
            if (!Schema::hasColumn('hospital_order_employees', 'time_from')) {
                $table->time('time_from')->nullable()->after('filed_date');
            }
            if (!Schema::hasColumn('hospital_order_employees', 'time_to')) {
                $table->time('time_to')->nullable()->after('time_from');
            }
            if (!Schema::hasColumn('hospital_order_employees', 'status')) {
                $table->string('status', 20)->default('pending')->after('time_to');
            }
            if (!Schema::hasColumn('hospital_order_employees', 'remarks')) {
                $table->string('remarks', 500)->nullable()->after('status');
            }
            if (!Schema::hasColumn('hospital_order_employees', 'reviewed_by')) {
                $table->integer('reviewed_by')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('hospital_order_employees', 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        DB::table('hospital_order_employees')
            ->whereNull('filed_date')
            ->update([
                'filed_date' => DB::raw('DATE(created_at)'),
                'status' => 'approved',
            ]);

        try {
            Schema::table('hospital_order_employees', function (Blueprint $table) {
                $table->unique(['ho_id', 'userid', 'filed_date', 'time_from', 'time_to'], 'ho_emp_file_unique');
            });
        } catch (\Throwable $e) {
            // Ignore if index already exists.
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        try {
            Schema::table('hospital_order_employees', function (Blueprint $table) {
                $table->dropUnique('ho_emp_file_unique');
            });
        } catch (\Throwable $e) {
            // Ignore if index does not exist.
        }

        Schema::table('hospital_order_employees', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['filed_date', 'time_from', 'time_to', 'status', 'remarks', 'reviewed_by', 'reviewed_at'] as $column) {
                if (Schema::hasColumn('hospital_order_employees', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('hospital_orders', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['order_date', 'time_from', 'time_to'] as $column) {
                if (Schema::hasColumn('hospital_orders', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
}
