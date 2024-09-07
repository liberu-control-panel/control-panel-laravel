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
     * Test that the application is running in a Docker environment.
     */
    public function test_application_is_running_in_docker_environment(): void
    {
        $this->assertTrue(Config::get('app.docker'), 'Application is not running in a Docker environment');
    }

    /**
     * Test that the database connection is working in the Docker environment.
     */
    public function test_database_connection_in_docker_environment(): void
    {
        $this->assertTrue(\DB::connection()->getPdo(), 'Database connection failed');
    }

    /**
     * Test that the Redis connection is working in the Docker environment.
     */
    public function test_redis_connection_in_docker_environment(): void
    {
        $this->assertTrue(\Redis::connection()->ping(), 'Redis connection failed');
    }
}
