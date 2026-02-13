<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeComplianceForToDateInSalnTables extends Migration
{
    /**
     * The list of all SALN-related tables that have the compliance_for column.
     */
    private $tables = [
        'saln_declarant',
        'saln_spouse',
        'saln_children',
        'real_properties',
        'personal_properties',
        'liabilities',
        'business_interests',
        'government_relatives',
        'marriages',
        'spouse_real_properties',
        'spouse_personal_properties',
        'spouse_liabilities',
        'spouse_business_interests',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'compliance_for')) {
                // Step 1: Add a temporary date column
                Schema::table($tableName, function (Blueprint $table) {
                    $table->date('compliance_for_date')->nullable()->after('compliance_for');
                });

                // Step 2: Convert existing year values to dates (YYYY-12-31)
                DB::table($tableName)
                    ->whereNotNull('compliance_for')
                    ->update([
                        'compliance_for_date' => DB::raw("CONCAT(compliance_for, '-12-31')")
                    ]);

                // Step 3: Drop old column and rename new one
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('compliance_for');
                });

                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('compliance_for_date', 'compliance_for');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'compliance_for')) {
                // Step 1: Add a temporary year column
                Schema::table($tableName, function (Blueprint $table) {
                    $table->year('compliance_for_year')->nullable()->after('compliance_for');
                });

                // Step 2: Extract year from date
                DB::table($tableName)
                    ->whereNotNull('compliance_for')
                    ->update([
                        'compliance_for_year' => DB::raw("YEAR(compliance_for)")
                    ]);

                // Step 3: Drop old column and rename new one
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('compliance_for');
                });

                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('compliance_for_year', 'compliance_for');
                });
            }
        }
    }
}
