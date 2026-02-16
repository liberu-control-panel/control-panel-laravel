<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\SshController;
use App\Services\SshConnectionService;
use Tests\TestCase;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SshControllerTest extends TestCase
{
    use RefreshDatabase;

    protected SshController $controller;
    protected $sshService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sshService = Mockery::mock(SshConnectionService::class);
        $this->controller = new SshController($this->sshService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_ssh_key_pair()
    {
        $this->sshService->shouldReceive('generateKeyPair')
            ->once()
            ->with('', 2048)
            ->andReturn([
                'private_key' => 'test-private-key',
                'public_key' => 'test-public-key'
            ]);

        $request = new \Illuminate\Http\Request([]);
        $response = $this->controller->generateKeyPair($request);

        $this->assertEquals(201, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertArrayHasKey('public_key', $data);
        $this->assertArrayHasKey('private_key', $data);
    }

    /** @test */
    public function it_has_required_controller_methods()
    {
        $methods = [
            'generateKeyPair',
            'deployKeyToDomain',
            'deployKeyToServer',
            'testConnection',
            'createCredential',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->controller, $method),
                "Method {$method} does not exist"
            );
        }
    }
}
