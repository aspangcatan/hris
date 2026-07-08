<?php

namespace Tests\Feature;

use Tests\TestCase; // phpcs:ignore

class ExampleTest extends TestCase
{
    public function testBasicTest()
    {
        // '/' is itself the login page for unauthenticated users (see
        // routes/web.php) - it renders the login view directly rather than
        // redirecting to a separate route.
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('login');
    }
}
