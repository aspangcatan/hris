<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $schema = $this->resolveSchema();
        $cutoffTable = $schema ? $schema . '.cutoff_summaries' : 'cutoff_summaries';
        $masterTable = $schema ? $schema . '.cutoff_summaries_master' : 'cutoff_summaries_master';
        $masterExists = $schema ? $this->tableExists($schema, 'cutoff_summaries_master') : Schema::hasTable('cutoff_summaries_master');

        if (!$masterExists) {
            Schema::create($masterTable, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title', 150)->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->integer('generated_by')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->index(['period_start', 'period_end'], 'cutoff_summaries_master_period_index');
                $table->unique(['period_start', 'period_end', 'title'], 'cutoff_summaries_master_period_unique');
            });
        }

        if (!$this->columnExists($cutoffTable, 'cutoff_id')) {
            Schema::table($cutoffTable, function (Blueprint $table) {
                $table->unsignedBigInteger('cutoff_id')->nullable()->after('id');
                $table->index('cutoff_id');
            });
        }

        if ($this->columnExists($cutoffTable, 'period_start') && $this->columnExists($cutoffTable, 'period_end')) {
            $groups = DB::table($cutoffTable)
                ->select(
                    'period_start',
                    'period_end',
                    'remarks',
                    DB::raw('MIN(generated_by) as generated_by'),
                    DB::raw('MIN(generated_at) as generated_at'),
                    DB::raw('MIN(created_at) as created_at'),
                    DB::raw('MIN(updated_at) as updated_at')
                )
                ->groupBy('period_start', 'period_end', 'remarks')
                ->get();

            foreach ($groups as $group) {
                $masterQuery = DB::table($masterTable)
                    ->where('period_start', $group->period_start)
                    ->where('period_end', $group->period_end);

                if ($group->remarks === null) {
                    $masterQuery->whereNull('title');
                } else {
                    $masterQuery->where('title', $group->remarks);
                }

                $masterId = $masterQuery->value('id');
                if (!$masterId) {
                    $masterId = DB::table($masterTable)->insertGetId([
                        'title' => $group->remarks,
                        'period_start' => $group->period_start,
                        'period_end' => $group->period_end,
                        'generated_by' => $group->generated_by,
                        'generated_at' => $group->generated_at,
                        'created_at' => $group->created_at ?? now(),
                        'updated_at' => $group->updated_at ?? now(),
                    ]);
                }

                $update = DB::table($cutoffTable)
                    ->where('period_start', $group->period_start)
                    ->where('period_end', $group->period_end);

                if ($group->remarks === null) {
                    $update->whereNull('remarks');
                } else {
                    $update->where('remarks', $group->remarks);
                }

                $update->update(['cutoff_id' => $masterId]);
            }
        }

        if ($this->indexExists($cutoffTable, 'cutoff_summaries_employee_period_unique')) {
            Schema::table($cutoffTable, function (Blueprint $table) {
                $table->dropUnique('cutoff_summaries_employee_period_unique');
            });
        }

        if (!$this->indexExists($cutoffTable, 'cutoff_summaries_employee_cutoff_unique')) {
            Schema::table($cutoffTable, function (Blueprint $table) {
                $table->unique(['employee_id', 'cutoff_id'], 'cutoff_summaries_employee_cutoff_unique');
            });
        }
    }

    public function down()
    {
        $schema = $this->resolveSchema();
        $cutoffTable = $schema ? $schema . '.cutoff_summaries' : 'cutoff_summaries';
        $masterTable = $schema ? $schema . '.cutoff_summaries_master' : 'cutoff_summaries_master';

        if ($this->indexExists($cutoffTable, 'cutoff_summaries_employee_cutoff_unique')) {
            Schema::table($cutoffTable, function (Blueprint $table) {
                $table->dropUnique('cutoff_summaries_employee_cutoff_unique');
            });
        }

        if ($this->columnExists($cutoffTable, 'cutoff_id')) {
            Schema::table($cutoffTable, function (Blueprint $table) {
                $table->dropColumn('cutoff_id');
            });
        }

        Schema::dropIfExists($masterTable);
    }

    private function resolveSchema(): ?string
    {
        if ($this->tableExists('hris', 'cutoff_summaries')) {
            return 'hris';
        }

        return null;
    }

    private function tableExists(string $schema, string $table): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$schema, $table]
        );

        return $result && (int) $result->count > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = str_contains($table, '.') ? explode('.', $table)[0] : env('DB_DATABASE');
        $tableName = str_contains($table, '.') ? explode('.', $table)[1] : $table;

        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$schema, $tableName, $column]
        );

        return $result && (int) $result->count > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $schema = str_contains($table, '.') ? explode('.', $table)[0] : env('DB_DATABASE');
        $tableName = str_contains($table, '.') ? explode('.', $table)[1] : $table;

        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$schema, $tableName, $index]
        );

        return $result && (int) $result->count > 0;
    }
};
