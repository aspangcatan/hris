<?php

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionSeederDescriptionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_employee_permission_descriptions_are_updated_by_seeder(): void
    {
        (new PermissionSeeder())->run();

        $viewDescription = DB::table('hris.permissions')
            ->where('code', 'employee.view')
            ->value('description');

        $editDescription = DB::table('hris.permissions')
            ->where('code', 'employee.edit')
            ->value('description');

        $this->assertSame("View other employees' PDS (read-only)", $viewDescription);
        $this->assertSame("Edit other employees' PDS", $editDescription);
    }
}
