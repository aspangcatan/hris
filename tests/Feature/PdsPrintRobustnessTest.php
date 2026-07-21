<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PdsPrintRobustnessTest extends TestCase
{
    private int $userId = 9192;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            'id' => $this->userId, 'fname' => 'Robust', 'mname' => 'Check', 'lname' => 'PdsUser',
            'suffix' => '', 'username' => 'pds_robust_test', 'password' => 'x',
        ]);
        DB::table('hris.employees')->insertOrIgnore([
            'user_id' => $this->userId, 'sex' => 'MALE', 'civil_status' => 'SINGLE', 'citizenship' => 'Filipino',
        ]);
        DB::table('hris.addresses')->insertOrIgnore([
            ['user_id' => $this->userId, 'address_type' => 'Residential', 'city' => 'Cebu City', 'province' => 'Cebu'],
            ['user_id' => $this->userId, 'address_type' => 'Permanent', 'city' => 'Cebu City', 'province' => 'Cebu'],
        ]);
        DB::table('hris.work_experiences')->insert([
            'user_id' => $this->userId, 'position' => 'Nurse II', 'agency' => 'DOH - CSMC',
            'monthly_salary' => '30000', 'salary_grade' => '15-1', 'appointment_status' => 'Permanent',
            'is_government' => 1, 'is_current_work' => 1, 'working_from' => '2020-01-01', 'working_to' => '2027-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::table('hris.work_experiences')->where('user_id', $this->userId)->delete();
        DB::table('hris.family_backgrounds')->where('user_id', $this->userId)->delete();
        DB::table('hris.addresses')->where('user_id', $this->userId)->delete();
        DB::table('hris.employees')->where('user_id', $this->userId)->delete();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
    }

    private function actAsUser(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee']);
    }

    /** @test */
    public function printing_without_experience_rows_or_learning_rows_does_not_crash(): void
    {
        $this->actAsUser();

        // No experience_rows/learning_rows - this is exactly what the
        // admin Employee modal's Print PDS button sends today.
        $response = $this->withoutMiddleware()->get('/pds?id=0&pds_date=01%2F01%2F2027&sworn_date=01%2F01%2F2027');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** @test */
    public function printing_with_no_family_background_row_does_not_crash(): void
    {
        $this->actAsUser();

        // Deliberately no family_backgrounds row for this user.
        $response = $this->withoutMiddleware()->get('/pds?id=0&pds_date=01%2F01%2F2027&sworn_date=01%2F01%2F2027&experience_rows=23&learning_rows=24');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
