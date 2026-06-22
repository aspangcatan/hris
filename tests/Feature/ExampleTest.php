<?php

namespace Tests\Feature;

use Tests\TestCase; // phpcs:ignore

class ExampleTest extends TestCase
{
    public function testBasicTest()
    {
        // Root redirects to login for unauthenticated users
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
