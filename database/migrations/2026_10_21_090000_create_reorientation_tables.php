<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        ['module' => 'reorientation', 'code' => 'reorientation.check',  'description' => 'Check employee re-orientation post tests'],
        ['module' => 'reorientation', 'code' => 'reorientation.manage', 'description' => 'Manage re-orientation post test questions'],
    ];

    private array $levels = ['admin', 'hr', 'hrh'];

    public function up()
    {
        Schema::create('reorientation_questions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('module_no');
            $table->text('question');
            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['module_no', 'is_active']);
        });

        Schema::create('reorientation_submissions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedTinyInteger('module_no');
            $table->enum('status', ['submitted', 'passed', 'failed'])->default('submitted');
            $table->unsignedInteger('attempt_no')->default(1);
            $table->text('remarks')->nullable();
            $table->unsignedInteger('checked_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_no']);
            $table->index('status');
        });

        Schema::create('reorientation_answers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('question_id');
            // Snapshot of the question as it was answered, in case HR edits it later
            $table->text('question_text');
            $table->longText('answer');
            $table->timestamps();

            $table->unique(['submission_id', 'question_id']);
            $table->foreign('submission_id')->references('id')->on('reorientation_submissions')->onDelete('cascade');
        });

        foreach ($this->permissions as $p) {
            DB::table('hris.permissions')->updateOrInsert(
                ['code' => $p['code']],
                array_merge($p, ['created_at' => now(), 'updated_at' => now()])
            );

            $permId = DB::table('hris.permissions')->where('code', $p['code'])->value('id');
            foreach ($this->levels as $level) {
                DB::table('hris.access_level_permissions')->updateOrInsert(
                    ['level' => $level, 'permission_id' => $permId],
                    ['level' => $level, 'permission_id' => $permId, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down()
    {
        $codes  = array_column($this->permissions, 'code');
        $permIds = DB::table('hris.permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('hris.access_level_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('hris.user_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('hris.permissions')->whereIn('id', $permIds)->delete();

        Schema::dropIfExists('reorientation_answers');
        Schema::dropIfExists('reorientation_submissions');
        Schema::dropIfExists('reorientation_questions');
    }
};
