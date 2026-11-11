<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave ledger, Phase 6.
 *  - leave.certify_opening: certify opening balances. Given to the HRH level;
 *    anyone else can be granted it per employee (client decision 2026-11-11).
 *  - The ledger's source key stops being unique: one leave date can legitimately
 *    be charged, returned and charged again (a rejection that is overturned).
 */
return new class extends Migration
{
    private const CODE = 'leave.certify_opening';

    public function up()
    {
        DB::transaction(function () {
            $id = DB::table('hris.permissions')->where('code', self::CODE)->value('id');
            if (!$id) {
                $id = DB::table('hris.permissions')->insertGetId([
                    'module' => 'leave',
                    'code' => self::CODE,
                    'description' => 'Certify employees\' opening leave balances',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if (!DB::table('hris.access_level_permissions')->where('level', 'hrh')->where('permission_id', $id)->exists()) {
                DB::table('hris.access_level_permissions')->insert([
                    'level' => 'hrh', 'permission_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        Schema::table('leave_credit_ledger', function (Blueprint $table) {
            $table->dropUnique('leave_ledger_source_unique');
            $table->index(['source_type', 'source_id'], 'leave_ledger_source_index');
        });
    }

    public function down()
    {
        Schema::table('leave_credit_ledger', function (Blueprint $table) {
            $table->dropIndex('leave_ledger_source_index');
            $table->unique(['source_type', 'source_id', 'leave_type_id', 'transaction_type'], 'leave_ledger_source_unique');
        });

        DB::transaction(function () {
            $ids = DB::table('hris.permissions')->where('code', self::CODE)->pluck('id');
            DB::table('hris.user_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('hris.access_level_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('hris.permissions')->whereIn('id', $ids)->delete();
        });
    }
};
