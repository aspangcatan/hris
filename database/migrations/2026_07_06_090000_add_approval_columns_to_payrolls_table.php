<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'cutoff_id')) {
                $table->unsignedBigInteger('cutoff_id')->nullable()->after('id');
                $table->index('cutoff_id');
            }
            if (!Schema::hasColumn('payrolls', 'reviewed_by')) {
                $table->integer('reviewed_by')->nullable()->after('prepared_by');
            }
            if (!Schema::hasColumn('payrolls', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('payrolls', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('approved_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'released_at')) {
                $table->dropColumn('released_at');
            }
            if (Schema::hasColumn('payrolls', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('payrolls', 'reviewed_by')) {
                $table->dropColumn('reviewed_by');
            }
            if (Schema::hasColumn('payrolls', 'cutoff_id')) {
                $table->dropIndex(['cutoff_id']);
                $table->dropColumn('cutoff_id');
            }
        });
    }
};
