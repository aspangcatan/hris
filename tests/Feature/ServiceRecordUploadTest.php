<?php

namespace Tests\Feature;

use App\Http\Controllers\ServiceRecordsController;
use App\Models\ServiceRecords;
use App\Services\ServiceRecordExcelParser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ServiceRecordUploadTest extends TestCase
{
    private int $userId = 1116;

    private array $preExistingIds = [];

    private array $tempFiles = [];

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
        foreach ($this->tempFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /**
     * Builds a workbook laid out like SERVICE_RECORD_TEMPLATE.xlsx (header H10, data from H13, footer after).
     */
    private function makeWorkbook(array $name = ['PANGCATAN', 'ASNAUI', 'OPTINA']): string
    {
        $book = new Spreadsheet();
        $ws = $book->getActiveSheet();
        $ws->setCellValue('H1', 'SERVICE RECORD');
        $ws->setCellValue('H3', 'NAME :');
        $ws->setCellValue('I3', $name[0]);
        $ws->setCellValue('K3', $name[1]);
        $ws->setCellValue('N3', $name[2]);
        $ws->setCellValue('H10', 'Services Inclusive');
        $ws->setCellValue('J10', 'RECORD OF APPOINTMENT DESIGNATION');
        $ws->setCellValue('H12', 'From');
        $ws->setCellValue('I12', 'To');

        $office = "Cebu South Medical Center\n(Formerly Talisay District Hospital)";
        $rows = [
            // text date, serial date
            ['07/17/2023', ExcelDate::PHPToExcel(new \DateTime('2023-12-31')), 'Administrative Assistant I', 'Permanent', 223440, $office, 'None', '', 7],
            // serial, serial
            [ExcelDate::PHPToExcel(new \DateTime('2024-01-01')), ExcelDate::PHPToExcel(new \DateTime('2024-12-01')), 'Administrative Assistant I', 'Permanent', '232,380.00', $office, 'None', 'Promoted', null],
            // present
            ['01/01/2026', 'Present', 'Administrative Assistant II', 'Permanent', 269076, $office, null, null, null],
            // bad To date
            ['02/01/2026', 'sometime', 'Administrative Assistant II', 'Casual', 1000, $office, null, null, null],
        ];
        $r = 13;
        foreach ($rows as $row) {
            foreach (['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'] as $i => $col) {
                if ($row[$i] !== null) {
                    $ws->setCellValue($col . $r, $row[$i]);
                }
            }
            $r++;
        }
        $ws->setCellValue('H' . $r, 'Issued in compliance with Executive Order No. 54 dated August 10, 1954.');
        $ws->setCellValue('H' . ($r + 2), 'DATE ISSUED : January 23, 2026');

        $path = tempnam(sys_get_temp_dir(), 'srx') . '.xlsx';
        (new Xlsx($book))->save($path);
        $this->tempFiles[] = $path;
        return $path;
    }

    /** @test */
    public function parser_reads_rows_until_the_footer_with_mixed_date_formats()
    {
        $result = app(ServiceRecordExcelParser::class)->parse($this->makeWorkbook(), $this->userId);

        $this->assertCount(4, $result['rows']);

        $first = $result['rows'][0];
        $this->assertSame(13, $first['row']);
        $this->assertSame('2023-07-17', $first['date_from']);
        $this->assertSame('2023-12-31', $first['date_to']);
        $this->assertSame(0, $first['current_work']);
        $this->assertSame('Administrative Assistant I', $first['designation']);
        $this->assertSame('Permanent', $first['appointment_status']);
        $this->assertSame('223440', $first['monthly_salary']);
        $this->assertStringContainsString('Formerly Talisay', $first['office_assignment']);
        $this->assertSame('None', $first['leave_absence']);
        $this->assertSame('', $first['separation']);
        $this->assertSame([], $first['errors']);

        $second = $result['rows'][1];
        $this->assertSame('2024-01-01', $second['date_from']);
        $this->assertSame('2024-12-01', $second['date_to']);
        $this->assertSame('232380', $second['monthly_salary']);
        $this->assertSame('Promoted', $second['separation']);
    }

    /** @test */
    public function parser_marks_present_rows_and_flags_unparseable_dates()
    {
        $rows = app(ServiceRecordExcelParser::class)->parse($this->makeWorkbook(), $this->userId)['rows'];

        $this->assertSame(1, $rows[2]['current_work']);
        $this->assertNull($rows[2]['date_to']);
        $this->assertSame('', $rows[2]['leave_absence']);
        $this->assertSame([], $rows[2]['errors']);

        $this->assertNull($rows[3]['date_to']);
        $this->assertArrayHasKey('date_to', $rows[3]['errors']);
    }

    /** @test */
    public function parser_reports_name_match_and_mismatch()
    {
        $parser = app(ServiceRecordExcelParser::class);

        $match = $parser->parse($this->makeWorkbook(['pangcatan ', 'Asnaui', 'optina']), $this->userId);
        $this->assertFalse($match['name_mismatch']);

        $mismatch = $parser->parse($this->makeWorkbook(['ALDAVE', 'CHARMIE', 'BARGAMENTO']), $this->userId);
        $this->assertTrue($mismatch['name_mismatch']);
        $this->assertSame('ALDAVE, CHARMIE BARGAMENTO', $mismatch['file_name_text']);
        $this->assertSame('PANGCATAN, ASNAUI OPTINA', $mismatch['employee_name_text']);
    }

    /** @test */
    public function parse_upload_endpoint_returns_rows_without_saving()
    {
        $path = $this->makeWorkbook();
        $file = new UploadedFile($path, 'ALDAVE.xlsx', null, null, true);
        $request = Request::create('/service_records/parse', 'POST', ['user_id' => $this->userId], [], ['file' => $file]);

        $data = json_decode(app(ServiceRecordsController::class)->parseUpload($request)->getContent(), true);

        $this->assertSame(200, $data['code']);
        $this->assertSame('ALDAVE.xlsx', $data['file_name']);
        $this->assertCount(4, $data['rows']);
        $this->assertSame(0, ServiceRecords::where('user_id', $this->userId)->whereNotIn('id', $this->preExistingIds)->count());
    }

    /** @test */
    public function parse_route_accepts_a_multipart_upload()
    {
        $file = new UploadedFile($this->makeWorkbook(), 'ALDAVE.xlsx', null, null, true);

        $response = $this->actingAs(\App\Models\User::find($this->userId))
            ->post(route('parseServiceRecordUpload'), ['user_id' => $this->userId, 'file' => $file]);

        $response->assertOk();
        $this->assertSame(200, $response->json('code'));
        $this->assertCount(4, $response->json('rows'));
    }

    /** @test */
    public function parse_upload_rejects_a_missing_file()
    {
        $request = Request::create('/service_records/parse', 'POST', ['user_id' => $this->userId]);
        $data = json_decode(app(ServiceRecordsController::class)->parseUpload($request)->getContent(), true);
        $this->assertSame(500, $data['code']);
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'current_work' => 0,
            'designation' => 'Nurse I',
            'appointment_status' => 'Permanent',
            'monthly_salary' => '460956',
            'office_assignment' => 'CSMC',
            'leave_absence' => 'None',
            'separation' => '',
        ], $overrides);
    }

    /** @test */
    public function bulk_store_inserts_every_row_and_applies_the_present_rule()
    {
        $request = new Request(['user_id' => $this->userId, 'rows' => [
            $this->row(),
            $this->row(['date_from' => '2026-01-01', 'date_to' => null, 'current_work' => 1]),
        ]]);

        $data = json_decode(app(ServiceRecordsController::class)->bulkStore($request)->getContent(), true);

        $this->assertSame(200, $data['code']);
        $new = ServiceRecords::where('user_id', $this->userId)->whereNotIn('id', $this->preExistingIds)->orderBy('date_from')->get();
        $this->assertCount(2, $new);
        $this->assertSame('Nurse I', $new[0]->designation);
        $this->assertSame(1, (int) $new[1]->current_work);
        $this->assertSame('2026-01-01', date('Y-m-d', strtotime($new[1]->date_to)));
    }

    /** @test */
    public function bulk_store_saves_nothing_when_any_row_is_invalid()
    {
        $request = new Request(['user_id' => $this->userId, 'rows' => [
            $this->row(),
            $this->row(['designation' => '']),
        ]]);

        $data = json_decode(app(ServiceRecordsController::class)->bulkStore($request)->getContent(), true);

        $this->assertSame(500, $data['code']);
        $this->assertArrayHasKey(1, $data['errors']);
        $this->assertSame(0, ServiceRecords::where('user_id', $this->userId)->whereNotIn('id', $this->preExistingIds)->count());
    }

    /** @test */
    public function bulk_store_requires_date_to_unless_present()
    {
        $request = new Request(['user_id' => $this->userId, 'rows' => [
            $this->row(['date_to' => null, 'current_work' => 0]),
        ]]);

        $data = json_decode(app(ServiceRecordsController::class)->bulkStore($request)->getContent(), true);

        $this->assertSame(500, $data['code']);
        $this->assertSame(0, ServiceRecords::where('user_id', $this->userId)->whereNotIn('id', $this->preExistingIds)->count());
    }
}
