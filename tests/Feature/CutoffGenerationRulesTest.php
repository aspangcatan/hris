<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rules on the "Generate Cutoff" feature: payroll.settings permission,
 * previous month only, one month span, resigned/retired left out, and the
 * details modal reading rows by cutoff ID.
 */
class CutoffGenerationRulesTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;
    private int $resignedUserId;
    private string $title;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->title = 'Cutoff Rules Test ' . uniqid();
        $this->userId = $this->makeUser('CutoffRules');
        $this->resignedUserId = $this->makeUser('CutoffResigned');
        DB::table('hris.employees')->insert(['user_id' => $this->resignedUserId, 'employee_type' => 'Resigned']);

        Auth::guard('web')->login(User::on('users')->findOrFail($this->userId));
    }

    protected function tearDown(): void
    {
        $masterIds = DB::table('hris.cutoff_summaries_master')->where('title', $this->title)->pluck('id');
        DB::table('hris.cutoff_summary_days')->whereIn('cutoff_id', $masterIds)->delete();
        DB::table('hris.cutoff_summaries')->whereIn('cutoff_id', $masterIds)->delete();
        DB::table('hris.cutoff_summaries_master')->whereIn('id', $masterIds)->delete();

        foreach ([$this->userId, $this->resignedUserId] as $id) {
            DB::table('hris.user_permissions')->where('user_id', $id)->delete();
            DB::connection('users')->table('user_priv')->where('user_id', $id)->delete();
            DB::connection('users')->table('users')->where('id', $id)->delete();
        }
        parent::tearDown();
    }

    private function makeUser(string $fname): int
    {
        $id = DB::connection('users')->table('users')->insertGetId([
            'fname' => $fname, 'lname' => 'Test', 'mname' => '',
            'username' => strtolower($fname) . '_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $id],
            ['user_id' => $id, 'syscode' => 'hris', 'level' => 'standard']
        );

        return $id;
    }

    private function grantPayrollSettings(): void
    {
        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->userId,
            'permission_id' => DB::table('hris.permissions')->where('code', 'payroll.settings')->value('id'),
            'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    private function generate(string $start, string $end, array $employees)
    {
        return $this->postJson('/cutoff-summaries', [
            'title' => $this->title, 'start_date' => $start, 'end_date' => $end, 'employees' => $employees,
        ]);
    }

    /** @test */
    public function generating_or_viewing_cutoffs_needs_the_payroll_settings_permission()
    {
        $prev = now()->startOfMonth()->subMonth();

        $this->generate($prev->toDateString(), $prev->copy()->endOfMonth()->toDateString(), [$this->userId])
            ->assertStatus(403);
        $this->getJson('/cutoff-summary-preview?cutoff_id=1')->assertStatus(403);

        $this->assertFalse(DB::table('hris.cutoff_summaries_master')->where('title', $this->title)->exists());
    }

    /** @test */
    public function only_the_previous_month_can_be_generated()
    {
        $this->grantPayrollSettings();

        $twoMonthsAgo = now()->startOfMonth()->subMonths(2);
        $this->generate($twoMonthsAgo->toDateString(), $twoMonthsAgo->copy()->endOfMonth()->toDateString(), [$this->userId])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertSee('previous month', false);

        $thisMonth = now()->startOfMonth();
        $this->generate($thisMonth->toDateString(), $thisMonth->copy()->addDays(5)->toDateString(), [$this->userId])
            ->assertStatus(422);
    }

    /** @test */
    public function a_cutoff_cannot_span_two_months_but_a_half_month_is_fine()
    {
        $this->grantPayrollSettings();
        $prev = now()->startOfMonth()->subMonth();

        $this->generate($prev->copy()->addDays(14)->toDateString(), now()->startOfMonth()->addDays(5)->toDateString(), [$this->userId])
            ->assertStatus(422)
            ->assertSee('same month', false);

        $this->generate($prev->toDateString(), $prev->copy()->addDays(14)->toDateString(), [$this->userId])
            ->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function resigned_employees_are_left_out_and_details_are_read_by_cutoff_id()
    {
        $this->grantPayrollSettings();
        $prev = now()->startOfMonth()->subMonth();
        $start = $prev->toDateString();
        $end = $prev->copy()->endOfMonth()->toDateString();

        $response = $this->generate($start, $end, [$this->userId, $this->resignedUserId])->assertOk();
        $this->assertStringContainsString('1 resigned/retired employee was left out', $response->json('message'));

        $master = DB::table('hris.cutoff_summaries_master')->where('title', $this->title)->first();
        $this->assertNotNull($master);
        $ids = DB::table('hris.cutoff_summaries')->where('cutoff_id', $master->id)->pluck('employee_id')->map(fn($i) => (int) $i)->all();
        $this->assertSame([$this->userId], $ids);

        // Another cutoff with the SAME dates must not show up in this cutoff's details.
        $otherMasterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => $this->title . ' (other office)', 'period_start' => $start, 'period_end' => $end,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'cutoff_id' => $otherMasterId, 'employee_id' => $this->resignedUserId,
            'period_start' => $start, 'period_end' => $end, 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $rows = $this->getJson('/cutoff-summary-preview?cutoff_id=' . $master->id)->assertOk()->json('data');
            $this->assertSame([$this->userId], array_map(fn($r) => (int) $r['employee_id'], $rows));

            $this->getJson('/cutoff-summary-preview')->assertStatus(422);
        } finally {
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $otherMasterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $otherMasterId)->delete();
        }

        // All resigned -> refused with a clear message.
        $this->generate($start, $end, [$this->resignedUserId])
            ->assertStatus(422)
            ->assertSee('resigned or retired', false);
    }
}
