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
        if (Schema::hasTable('access_level_permissions')) { return; }
        Schema::create('access_level_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('level', 191);
            $table->unsignedBigInteger('permission_id')->index('access_level_permissions_permission_id_foreign');
            $table->timestamps();

            $table->unique(['level', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('access_level_permissions');
    }
};

