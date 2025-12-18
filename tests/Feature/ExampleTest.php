<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Homepage redirects unauthenticated users to login.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // Homepage redirects to /pim/login for unauthenticated users
        $response->assertRedirect('/pim/login');
    }
}
