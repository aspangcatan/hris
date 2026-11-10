<?php

namespace Tests\Feature;

use App\Services\LwopRuleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LWOP Settings (lwop_rule_sets): effective-date selection, the page's
 * save/lock/delete rules, and the rule set recorded on generated cutoffs.
 * Runs against the real hris database (like the other feature tests) and
 * removes everything it adds. Skipped until the 2026_11_10_2000xx
 * migrations have run.
 */
class LwopSettingsTest extends TestCase
{
    private int $userId = 9601;

    /** @var int[] */
    private array $ruleIds = [];

    /** @var int[] */
    private array $masterIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('lwop_rule_sets') || !Schema::hasColumn('cutoff_summaries', 'lwop_rule_set_id')) {
            $this->markTestSkipped('Run the LWOP Settings migrations (2026_11_10_200000/200001) first.');
        }
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        LwopRuleService::flush();
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('lwop_rule_sets')) {
            foreach ($this->masterIds as $masterId) {
                DB::table('hris.cutoff_summary_days')->where('cutoff_id', $masterId)->delete();
                DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
                DB::table('hris.cutoff_summaries_master')->where('id', $masterId)->delete();
            }
            DB::table('lwop_rule_sets')->whereIn('id', $this->ruleIds)->delete();
            DB::table('lwop_rule_sets')->where('effective_from', '>=', '2031-01-01')->where('remarks', 'like', 'test:%')->delete();
            DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        }
        LwopRuleService::flush();
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function addRuleSet(string $effectiveFrom, array $values = []): int
    {
        $id = DB::table('lwop_rule_sets')->insertGetId($values + [
            'effective_from' => $effectiveFrom,
            'remarks' => 'test: ' . $effectiveFrom,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->ruleIds[] = $id;
        LwopRuleService::flush();

        return $id;
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'effective_from' => '2031-03-01',
            'legal_basis' => 'Test circular',
            'remarks' => 'test: api',
            'count_rest_days_in_long_runs' => true,
            'long_run_min_days' => 5,
            'rest_day_source' => 'weekend',
            'holidays_bridge_runs' => true,
            'partial_day_mode' => 'fraction',
            'partial_day_breaks_run' => true,
            'absences_count_without_pay' => true,
            'absences_join_runs' => true,
            'month_basis_days' => 30,
            'fractional_key_mode' => 'interpolate',
        ];
    }

    /** A cutoff summary row that used $ruleId (locks it). */
    private function useRuleSetInACutoff(int $ruleId): void
    {
        $masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'LWOP settings lock test', 'period_start' => '2031-04-01', 'period_end' => '2031-04-30',
            'generated_by' => $this->userId, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->masterIds[] = $masterId;
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $masterId,
            'period_start' => '2031-04-01', 'period_end' => '2031-04-30',
            'total_working_days' => 22, 'days_present' => 22, 'days_absent' => 0,
            'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_rule_set_id' => $ruleId,
            'generated_by' => $this->userId, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @test */
    public function the_rule_set_in_force_on_a_date_is_the_latest_effective_on_or_before_it()
    {
        $base = (new LwopRuleService())->forDate('2030-12-31');
        $newId = $this->addRuleSet('2031-01-01', ['long_run_min_days' => 3, 'rest_day_source' => 'weekend']);

        $before = (new LwopRuleService())->forDate('2030-12-31');
        $after = (new LwopRuleService())->forDate('2031-02-15');

        $this->assertSame($base->id, $before->id, 'Dates before the new rule set keep the older one.');
        $this->assertSame($newId, $after->id);
        $this->assertSame(3, $after->long_run_min_days);
        $this->assertSame('weekend', $after->rest_day_source);
    }

    /** @test */
    public function the_page_adds_edits_and_deletes_an_unused_rule_set()
    {
        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/lwop-settings/save', $this->payload())->assertOk();
        $id = (int) DB::table('lwop_rule_sets')->where('effective_from', '2031-03-01')->value('id');
        $this->ruleIds[] = $id;
        $this->assertSame($this->userId, (int) DB::table('lwop_rule_sets')->where('id', $id)->value('created_by'));

        $this->withoutMiddleware()->postJson('/lwop-settings/save', $this->payload(['id' => $id, 'long_run_min_days' => 6]))->assertOk();
        $this->assertSame(6, (int) DB::table('lwop_rule_sets')->where('id', $id)->value('long_run_min_days'));

        $list = $this->withoutMiddleware()->getJson('/lwop-settings/list')->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $id);
        $this->assertFalse($row['locked']);
        $this->assertFalse($row['is_base']);

        $this->withoutMiddleware()->deleteJson('/lwop-settings/delete', ['id' => $id])->assertOk();
        $this->assertFalse(DB::table('lwop_rule_sets')->where('id', $id)->exists());
    }

    /** @test */
    public function the_page_refuses_duplicates_dates_before_the_base_and_deleting_the_base()
    {
        $this->actAsAdmin();
        $baseId = (int) DB::table('lwop_rule_sets')->orderBy('effective_from')->value('id');
        $baseDate = (string) DB::table('lwop_rule_sets')->where('id', $baseId)->value('effective_from');
        $this->addRuleSet('2031-03-01');

        $this->withoutMiddleware()->postJson('/lwop-settings/save', $this->payload())->assertStatus(422);
        $this->withoutMiddleware()->postJson('/lwop-settings/save', $this->payload(['effective_from' => '1990-01-01']))->assertStatus(422);
        $this->withoutMiddleware()->postJson('/lwop-settings/save', $this->payload(['id' => $baseId, 'effective_from' => '2031-05-01']))->assertStatus(422);
        $this->withoutMiddleware()->deleteJson('/lwop-settings/delete', ['id' => $baseId])->assertStatus(422);

        $this->assertSame($baseDate, (string) DB::table('lwop_rule_sets')->where('id', $baseId)->value('effective_from'));
    }

    /** @test */
    public function a_rule_set_used_by_a_cutoff_is_locked()
    {
        $this->actAsAdmin();
        $id = $this->addRuleSet('2031-03-01');
        $this->useRuleSetInACutoff($id);

        $this->withoutMiddleware()->postJson('/lwop-settings/save', $this->payload(['id' => $id, 'long_run_min_days' => 2]))->assertStatus(422);
        $this->withoutMiddleware()->deleteJson('/lwop-settings/delete', ['id' => $id])->assertStatus(422);

        $row = collect($this->withoutMiddleware()->getJson('/lwop-settings/list')->json('data'))->firstWhere('id', $id);
        $this->assertTrue($row['locked']);
        $this->assertSame(1, $row['cutoffs_using']);
    }

    /** @test */
    public function a_generated_cutoff_records_the_rule_set_it_used()
    {
        $summary = app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->userId, '2026-06-01', '2026-06-30', 'LWOP settings E2E Test Cutoff', $this->userId
        );
        $this->masterIds[] = (int) $summary->cutoff_id;

        $expected = (new LwopRuleService())->forDate('2026-06-01')->id;
        $this->assertNotNull($expected);
        $this->assertSame($expected, (int) $summary->lwop_rule_set_id);
    }
}
