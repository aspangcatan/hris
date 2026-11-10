<?php

namespace Tests\Feature;

use App\Services\LwopCaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LWOP Settings page (lwop_weekend_cases): add, edit, delete and refusals,
 * and that the saved cases are what the LWOP computation reads. Runs against
 * the real hris database and puts the cases back exactly as it found them.
 * Skipped until migration 2026_11_10_500000 has run.
 */
class LwopSettingsTest extends TestCase
{
    private int $userId = 9601;

    /** @var array<int, array> the cases before the test */
    private array $originalCases = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('lwop_weekend_cases')) {
            $this->markTestSkipped('Run migration 2026_11_10_500000 first.');
        }
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->originalCases = DB::table('lwop_weekend_cases')->get()->map(fn($r) => (array) $r)->all();
        DB::table('lwop_weekend_cases')->delete();
        LwopCaseService::flush();
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('lwop_weekend_cases')) {
            DB::table('lwop_weekend_cases')->delete();
            if (!empty($this->originalCases)) {
                DB::table('lwop_weekend_cases')->insert($this->originalCases);
            }
        }
        LwopCaseService::flush();
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function save(array $values)
    {
        return $this->withoutMiddleware()->postJson('/lwop-settings/save', $values + [
            'friday' => 'lwop', 'monday' => 'present', 'saturday' => true, 'sunday' => true,
        ]);
    }

    /** @test */
    public function the_page_adds_edits_lists_and_deletes_a_case()
    {
        $this->actAsAdmin();

        $this->save(['remarks' => 'HR memo'])->assertOk();
        $id = (int) DB::table('lwop_weekend_cases')->value('id');
        $this->assertSame($this->userId, (int) DB::table('lwop_weekend_cases')->where('id', $id)->value('created_by'));

        // A save replaces the whole case (the modal always sends every field).
        $this->save(['id' => $id, 'sunday' => false, 'remarks' => 'HR memo'])->assertOk();
        $this->assertSame(0, (int) DB::table('lwop_weekend_cases')->where('id', $id)->value('sunday_lwop'));

        $row = collect($this->withoutMiddleware()->getJson('/lwop-settings/list')->assertOk()->json('data'))->firstWhere('id', $id);
        $this->assertSame(['lwop', 'present', true, false, 'HR memo'], [$row['friday'], $row['monday'], $row['saturday'], $row['sunday'], $row['remarks']]);

        $this->withoutMiddleware()->deleteJson('/lwop-settings/delete', ['id' => $id])->assertOk();
        $this->assertSame(0, DB::table('lwop_weekend_cases')->count());
    }

    /** @test */
    public function the_page_refuses_a_second_case_for_the_same_friday_and_monday()
    {
        $this->actAsAdmin();
        $this->save([])->assertOk();

        $this->save(['saturday' => false])->assertStatus(422);
        $this->save(['friday' => 'holiday'])->assertStatus(422);
        $this->assertSame(1, DB::table('lwop_weekend_cases')->count());
    }

    /** @test */
    public function saved_cases_are_what_the_lwop_computation_reads()
    {
        $this->actAsAdmin();
        $this->save(['friday' => 'absent', 'monday' => 'absent', 'saturday' => true, 'sunday' => false])->assertOk();

        $this->assertSame(['absent|absent' => ['saturday' => true, 'sunday' => false]], (new LwopCaseService())->cases());
    }
}
