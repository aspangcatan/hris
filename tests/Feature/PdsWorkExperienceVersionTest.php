<?php

namespace Tests\Feature;

use Tests\TestCase;

class PdsWorkExperienceVersionTest extends TestCase
{
    private function sampleWorkExperience(): array
    {
        return [
            [
                'working_from' => '01/2020',
                'working_to' => 'present',
                'position' => 'Nurse II',
                'agency' => 'DOH - CSMC',
                'monthly_salary' => '30000',
                'salary_grade' => '15-1',
                'appointment_status' => 'Permanent',
                'is_government' => 'Y',
            ],
        ];
    }

    private function renderPds2(string $pdsVersion): string
    {
        return view('pdf.new.pds_2', [
            'eligibility' => [],
            'work_experience' => $this->sampleWorkExperience(),
            'next_page_work' => [],
            'pds_date' => '01/01/2027',
            'pds_version' => $pdsVersion,
        ])->render();
    }

    private function renderPds6(string $pdsVersion): string
    {
        return view('pdf.new.pds_6', [
            'work_experience' => $this->sampleWorkExperience(),
            'pds_date' => '01/01/2027',
            'pds_version' => $pdsVersion,
        ])->render();
    }

    /** @test */
    public function pds_2026_still_shows_monthly_salary_and_salary_grade(): void
    {
        $html = $this->renderPds2('2026');

        $this->assertStringContainsString('MONTHLY', $html);
        $this->assertStringContainsString('SALARY', $html);
        $this->assertStringContainsString('SALARY/JOB', $html);
        $this->assertStringContainsString('30000', $html);
        $this->assertStringContainsString('15-1', $html);
        $this->assertStringContainsString('colspan="8"', $html);
        $this->assertStringContainsString('CS FORM 212 (Revised 2026), Page 2 of 4', $html);
    }

    /** @test */
    public function pds_2025_hides_monthly_salary_and_salary_grade_and_reflows_colspan(): void
    {
        $html = $this->renderPds2('2025');

        $this->assertStringNotContainsString('MONTHLY', $html);
        $this->assertStringNotContainsString('SALARY/JOB', $html);
        $this->assertStringNotContainsString('30000', $html);
        $this->assertStringNotContainsString('15-1', $html);
        $this->assertStringContainsString('colspan="6"', $html);
        $this->assertStringNotContainsString('colspan="8"', $html);
        $this->assertStringContainsString('CS FORM 212 (Revised 2025), Page 2 of 4', $html);
    }

    /** @test */
    public function pds_6_continuation_matches_pds_2_behavior_per_version(): void
    {
        $html2026 = $this->renderPds6('2026');
        $this->assertStringContainsString('MONTHLY', $html2026);
        $this->assertStringContainsString('30000', $html2026);
        $this->assertStringContainsString('colspan="8"', $html2026);

        $html2025 = $this->renderPds6('2025');
        $this->assertStringNotContainsString('MONTHLY', $html2025);
        $this->assertStringNotContainsString('30000', $html2025);
        $this->assertStringContainsString('colspan="6"', $html2025);
    }
}
