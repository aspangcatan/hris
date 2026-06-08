<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->unsignedBigInteger('cutoff_id')->nullable()->after('id');
            $table->index('cutoff_id');
        });
    }

    public function down()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['cutoff_id']);
            $table->dropColumn('cutoff_id');
        });
    }
};
