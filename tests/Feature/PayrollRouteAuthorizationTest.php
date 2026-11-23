<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every payroll endpoint needs a payroll permission, not just a login. Before
 * this, any signed-in employee could edit the tax brackets or download every
 * employee's payslip (audit W3).
 */
class PayrollRouteAuthorizationTest extends TestCase
{
    private const VIEW = 'permission:payroll.view|payroll.manage|payroll.settings';

    public function guardedRoutes(): array
    {
        return [
            // Payroll settings and catalogs
            'getPayrollSettings' => ['getPayrollSettings', 'permission:payroll.settings|payroll.manage'],
            'savePayrollSettings' => ['savePayrollSettings', 'permission:payroll.settings|payroll.manage'],
            'getPayrollComponents' => ['getPayrollComponents', self::VIEW],
            'savePayrollComponents' => ['savePayrollComponents', 'permission:payroll.settings'],
            'getPayrollTaxRules' => ['getPayrollTaxRules', self::VIEW],
            'savePayrollTaxRules' => ['savePayrollTaxRules', 'permission:payroll.settings'],
            'getCutoffPeriods' => ['getCutoffPeriods', 'permission:payroll.view|payroll.manage|payroll.settings|cutoff.finalize|cutoff.reopen'],
            'getCutoffEmployeesForPayroll' => ['getCutoffEmployeesForPayroll', 'permission:payroll.manage|payroll.settings'],
            // Preparing, approving and releasing
            'savePayroll' => ['savePayroll', 'permission:payroll.manage'],
            'savePayrollEmployee' => ['savePayrollEmployee', 'permission:payroll.manage'],
            'submitPayrollForApproval' => ['submitPayrollForApproval', 'permission:payroll.manage'],
            'payrollApprovalAction' => ['payrollApprovalAction', 'permission:payroll.view|payroll.manage'],
            'releasePayroll' => ['releasePayroll', 'permission:payroll.manage'],
            // Reading pay data
            'getPayroll' => ['getPayroll', self::VIEW],
            'getPayrollEmployee' => ['getPayrollEmployee', self::VIEW],
            'exportPayroll' => ['exportPayroll', self::VIEW],
            'payrollPayslip' => ['payrollPayslip', self::VIEW],
        ];
    }

    /**
     * @test
     * @dataProvider guardedRoutes
     */
    public function payroll_route_requires_a_payroll_permission(string $name, string $expected)
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, "Route {$name} is missing.");

        $this->assertContains($expected, $route->gatherMiddleware(), "Route {$name} is not guarded by {$expected}.");
    }
}
