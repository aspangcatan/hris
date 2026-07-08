<?php

namespace Tests\Feature;

use App\Services\DTRServices;
use App\Services\LeaveCreditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CreditControllerTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_updates_credit_balance_for_employees()
    {
        // Mock employee data
        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => 8999, 'division' => 1, 'section' => 1, 'fname' => 'Test', 'lname' => 'User', 'username' => 'credit_test_8999', 'password' => 'x']
        ]);
        DB::table('hris.schedules')->insertOrIgnore([
            ['employee_id' => 8999, 'schedule_date' => '2025-09-05']
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

        // Route sits behind the 'auth' middleware group (routes/web.php:133);
        // this test exercises controller wiring, not auth, so bypass it -
        // same pattern used by the other feature tests in this suite.
        // The controller filters tdh_user.users.division/section directly
        // (numeric FK ids - confirmed against credit_management.blade.php,
        // whose division/section <select> option values are ids, not names),
        // so these must match the seeded user's numeric division/section above.
        $response = $this->withoutMiddleware()->postJson('/update_credit_balance', [
            'month' => 9,
            'year' => 2025,
            'division' => 1,
            'section' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Success']);
    }
}
