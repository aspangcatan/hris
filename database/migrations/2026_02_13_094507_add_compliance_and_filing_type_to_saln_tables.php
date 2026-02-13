<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddComplianceAndFilingTypeToSalnTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add compliance_for and filing_type to saln_declarant table
        Schema::table('saln_declarant', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('date_issued');
            $table->enum('filing_type', ['Joint Filing', 'Separate Filing', 'Not Applicable'])->default('Not Applicable')->after('compliance_for');
            $table->decimal('total_assets', 15, 2)->default(0)->after('filing_type');
            $table->decimal('total_liabilities', 15, 2)->default(0)->after('total_assets');
            $table->decimal('net_worth', 15, 2)->default(0)->after('total_liabilities');
            $table->timestamps();
        });

        // Add compliance_for to saln_spouse table
        Schema::table('saln_spouse', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('date_issued');
            $table->decimal('total_assets', 15, 2)->default(0)->after('compliance_for');
            $table->decimal('total_liabilities', 15, 2)->default(0)->after('total_assets');
            $table->decimal('net_worth', 15, 2)->default(0)->after('total_liabilities');
            $table->timestamps();
        });

        // Add compliance_for to all related tables for historical tracking
        Schema::table('saln_children', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('birthdate');
        });

        Schema::table('real_properties', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('acquisition_cost');
        });

        Schema::table('personal_properties', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('acquisition_cost');
        });

        Schema::table('liabilities', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('outstanding_balance');
        });

        Schema::table('business_interests', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('date_acquisition');
        });

        Schema::table('government_relatives', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('agency');
        });

        Schema::table('marriages', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('spouse');
        });

        // Spouse related tables
        Schema::table('spouse_real_properties', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('acquisition_cost');
        });

        Schema::table('spouse_personal_properties', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('acquisition_cost');
        });

        Schema::table('spouse_liabilities', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('outstanding_balance');
        });

        Schema::table('spouse_business_interests', function (Blueprint $table) {
            $table->year('compliance_for')->nullable()->after('date_acquisition');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('saln_declarant', function (Blueprint $table) {
            $table->dropColumn(['compliance_for', 'filing_type', 'total_assets', 'total_liabilities', 'net_worth', 'created_at', 'updated_at']);
        });

        Schema::table('saln_spouse', function (Blueprint $table) {
            $table->dropColumn(['compliance_for', 'total_assets', 'total_liabilities', 'net_worth', 'created_at', 'updated_at']);
        });

        Schema::table('saln_children', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('real_properties', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('personal_properties', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('liabilities', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('business_interests', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('government_relatives', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('marriages', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('spouse_real_properties', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('spouse_personal_properties', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('spouse_liabilities', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });

        Schema::table('spouse_business_interests', function (Blueprint $table) {
            $table->dropColumn('compliance_for');
        });
    }
}
