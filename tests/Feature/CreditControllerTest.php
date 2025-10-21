<?php

namespace Tests\Feature;

use App\Services\DTRServices;
use App\Services\LeaveCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CreditControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_updates_credit_balance_for_employees()
    {
        // Mock employee data
        DB::table('tdh_user.users')->insert([
            ['id' => 1, 'division' => 'IT', 'section' => 'Development']
        ]);
        DB::table('hris.schedules')->insert([
            ['employee_id' => 1, 'schedule_date' => '2025-09-05']
        ]);

        // Mock DTR and LeaveCredit services
        $mockDTR = Mockery::mock(DTRServices::class);
        $mockLeaveCredit = Mockery::mock(LeaveCreditService::class);

        $mockDTR->shouldReceive('getTotalPresent')->andReturn(22);
        $mockDTR->shouldReceive('getTotalLateAbsent')->andReturn([
            'late_minutes' => 0, 'undertime_minutes' => 0, 'absences' => 0
        ]);

        $mockLeaveCredit->shouldReceive('computeEarnedCredits')->andReturn([
            'monthly_points_earned' => 1.5,
            'total_deductions' => 0,
            'net_points' => 1.5
        ]);
        $mockLeaveCredit->shouldReceive('updateEarnedCredits')->once();

        $this->app->instance(DTRServices::class, $mockDTR);
        $this->app->instance(LeaveCreditService::class, $mockLeaveCredit);

        $response = $this->postJson('/update-credit-balance', [
            'month' => 9,
            'year' => 2025,
            'division' => 'IT',
            'section' => 'Development',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Success']);
    }
}
