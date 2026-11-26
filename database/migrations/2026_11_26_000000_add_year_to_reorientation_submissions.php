<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Re-orientation recurs every year: one submission per employee, module and year.
// Earlier years' submissions (and their answers) are kept as history.
return new class extends Migration
{
    public function up()
    {
        Schema::table('reorientation_submissions', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('module_no');
        });

        DB::statement('UPDATE reorientation_submissions SET year = YEAR(COALESCE(submitted_at, created_at, NOW()))');

        Schema::table('reorientation_submissions', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable(false)->change();
            $table->dropUnique(['user_id', 'module_no']);
            $table->unique(['user_id', 'module_no', 'year']);
            $table->index(['year', 'module_no']);
        });
    }

    public function down()
    {
        // Keeps only the latest year per employee and module, so the old unique key fits
        DB::statement('DELETE s FROM reorientation_submissions s
            JOIN reorientation_submissions n ON n.user_id = s.user_id AND n.module_no = s.module_no AND n.year > s.year');

        Schema::table('reorientation_submissions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'module_no', 'year']);
            $table->dropIndex(['year', 'module_no']);
            $table->unique(['user_id', 'module_no']);
            $table->dropColumn('year');
        });
    }
};
