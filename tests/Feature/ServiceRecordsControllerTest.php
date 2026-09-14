<?php

namespace Tests\Feature;

use App\Http\Controllers\ServiceRecordsController;
use App\Models\ServiceRecords;
use Illuminate\Http\Request;
use Tests\TestCase;

class ServiceRecordsControllerTest extends TestCase
{
    private int $userId = 1116;

    private array $preExistingIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->preExistingIds = ServiceRecords::where('user_id', $this->userId)->pluck('id')->all();
    }

    protected function tearDown(): void
    {
        ServiceRecords::where('user_id', $this->userId)
            ->whereNotIn('id', $this->preExistingIds)
            ->forceDelete();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => 0,
            'user_id' => $this->userId,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'current_work' => 0,
            'designation' => 'Nurse II',
            'appointment_status' => 'Permanent',
            'monthly_salary' => '30000',
            'office_assignment' => 'DOH - CSMC',
            'leave_absence' => '',
            'separation' => '',
        ], $overrides);
    }

    /** @test */
    public function insert_response_includes_the_new_records_id()
    {
        $controller = app(ServiceRecordsController::class);

        $response = $controller->insertUpdateServiceRecords(new Request($this->payload()));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $data['code']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotNull($data['id']);
        $this->assertTrue(ServiceRecords::where('id', $data['id'])->where('user_id', $this->userId)->exists());
    }

    /** @test */
    public function editing_a_just_inserted_record_updates_it_instead_of_creating_a_duplicate()
    {
        $controller = app(ServiceRecordsController::class);

        $insertResponse = $controller->insertUpdateServiceRecords(new Request($this->payload()));
        $insertData = json_decode($insertResponse->getContent(), true);
        $newId = $insertData['id'];

        $controller->insertUpdateServiceRecords(new Request($this->payload([
            'id' => $newId,
            'designation' => 'Nurse III',
        ])));

        $newRecordsCount = ServiceRecords::where('user_id', $this->userId)
            ->whereNotIn('id', $this->preExistingIds)
            ->count();
        $this->assertSame(1, $newRecordsCount);
        $this->assertSame('Nurse III', ServiceRecords::find($newId)->designation);
    }
}
