<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class ExampleTest extends TestCase
{
    /**
     * Test the root route ("/") returns a successful response.
     */
    public function test_the_root_route_returns_a_successful_response(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    /**
     * Test the "/app" route returns a successful response.
     */
    public function test_the_app_route_returns_a_successful_response(): void
    {
        $response = $this->get('/app');
        $response->assertStatus(200);
    }

    /**
     * Test the "/admin" route returns a successful response.
     */
    public function test_the_admin_route_returns_a_successful_response(): void
    {
        $response = $this->get('/admin');
        $response->assertStatus(200);
    }

    /**
     * Test that the application environment is properly configured.
     */
    public function test_application_environment_is_configured(): void
    {
        $this->assertNotEmpty(config('app.name'), 'Application name should be configured');
        $this->assertNotEmpty(config('app.key'), 'Application key should be configured');
    }
}
