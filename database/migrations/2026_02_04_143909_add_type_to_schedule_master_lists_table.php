<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToScheduleMasterListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('schedule_master_lists', function (Blueprint $table) {
            $table->string('type', 20)->default('FIXED')->after('code'); // FIXED or GLIDING
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('schedule_master_lists', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
