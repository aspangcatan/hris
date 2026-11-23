<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * An active tax purpose for creating a payroll (Tax Purpose is required).
     * payroll_tax_rules is InnoDB, so a DatabaseTransactions test rolls it back.
     */
    protected function testTaxPurpose(): string
    {
        $purpose = 'TEST TAX PURPOSE';
        if (!DB::table('hris.payroll_tax_rules')->where('purpose', $purpose)->where('is_active', 1)->exists()) {
            DB::table('hris.payroll_tax_rules')->insert([
                'purpose' => $purpose, 'min_annual' => 0, 'max_annual' => null, 'rate_percent' => 0,
                'tax_mode' => 'progressive', 'basis' => 'monthly', 'is_active' => 1, 'sort_order' => 0,
            ]);
        }

        return $purpose;
    }
}
