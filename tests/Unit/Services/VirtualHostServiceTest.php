<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VirtualHost;
use App\Services\DeploymentDetectionService;
use App\Services\StandaloneServiceHelper;
use App\Services\VirtualHostService;
use Tests\TestCase;
use Mockery;

class VirtualHostServiceTest extends TestCase
{
    protected VirtualHostService $service;
    protected $detectionService;
    protected $standaloneHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detectionService = Mockery::mock(DeploymentDetectionService::class);
        $this->standaloneHelper  = Mockery::mock(StandaloneServiceHelper::class);

        $this->service = new VirtualHostService(
            $this->detectionService,
            $this->standaloneHelper
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // getSystemUsername tests
    // ------------------------------------------------------------------

    /** @test */
    public function it_derives_system_username_with_cp_user_prefix()
    {
        $user = User::factory()->make(['id' => 1, 'username' => 'alice']);
        $virtualHost = VirtualHost::factory()->make(['user_id' => 1]);
        $virtualHost->setRelation('user', $user);

        $username = $this->callProtected('getSystemUsername', $virtualHost);

        $this->assertStringStartsWith('cp-user-', $username);
        $this->assertStringContainsString('alice', $username);
    }

    /** @test */
    public function it_sanitises_uppercase_and_special_chars_in_username()
    {
        $user = User::factory()->make(['id' => 2, 'username' => 'Bob.Smith!@#']);
        $virtualHost = VirtualHost::factory()->make(['user_id' => 2]);
        $virtualHost->setRelation('user', $user);

        $username = $this->callProtected('getSystemUsername', $virtualHost);

        // Must be lowercase alphanumeric/hyphens/underscores only
        $this->assertMatchesRegularExpression('/^[a-z0-9_-]+$/', $username);
        // Must carry the prefix so it matches the sudoers cp-user-* pattern
        $this->assertStringStartsWith('cp-user-', $username);
    }

    /** @test */
    public function it_falls_back_to_cp_user_id_when_username_is_null()
    {
        $user = User::factory()->make(['id' => 5, 'username' => null]);
        $virtualHost = VirtualHost::factory()->make(['user_id' => 5]);
        $virtualHost->setRelation('user', $user);

        $username = $this->callProtected('getSystemUsername', $virtualHost);

        $this->assertSame('cp-user-5', $username);
    }

    /** @test */
    public function it_prepends_u_when_sanitised_username_starts_with_digit()
    {
        $user = User::factory()->make(['id' => 3, 'username' => '123user']);
        $virtualHost = VirtualHost::factory()->make(['user_id' => 3]);
        $virtualHost->setRelation('user', $user);

        $username = $this->callProtected('getSystemUsername', $virtualHost);

        // After sanitisation '123user' → 'u123user', then prefixed as 'cp-user-u123user'
        $this->assertStringStartsWith('cp-user-', $username);
        $this->assertMatchesRegularExpression('/^[a-z_]/', ltrim($username, 'cp-user-'));
    }

    /** @test */
    public function it_truncates_combined_username_to_32_characters()
    {
        $longName = str_repeat('a', 50);
        $user = User::factory()->make(['id' => 4, 'username' => $longName]);
        $virtualHost = VirtualHost::factory()->make(['user_id' => 4]);
        $virtualHost->setRelation('user', $user);

        $username = $this->callProtected('getSystemUsername', $virtualHost);

        $this->assertLessThanOrEqual(32, strlen($username));
        $this->assertStringStartsWith('cp-user-', $username);
    }

    // ------------------------------------------------------------------
    // generateNginxConfig per-user PHP-FPM socket tests
    // ------------------------------------------------------------------

    /** @test */
    public function it_uses_per_user_php_fpm_socket_in_standalone_mode()
    {
        $user = User::factory()->make(['id' => 1, 'username' => 'alice']);
        $virtualHost = VirtualHost::factory()->make([
            'user_id'       => 1,
            'hostname'      => 'alice.example.com',
            'document_root' => '/var/www/alice.example.com',
            'php_version'   => '8.3',
            'ssl_enabled'   => false,
        ]);
        $virtualHost->setRelation('user', $user);

        $this->detectionService->shouldReceive('isStandalone')->andReturn(true);
        // getSystemUsername() will produce 'cp-user-alice'
        $this->standaloneHelper->shouldReceive('getPhpFpmSocketPath')
            ->with('cp-user-alice', '8.3')
            ->once()
            ->andReturn('/run/php/php8.3-fpm-cp-user-alice.sock');

        $config = $this->callProtected('generateNginxConfig', $virtualHost);

        $this->assertStringContainsString('unix:/run/php/php8.3-fpm-cp-user-alice.sock', $config);
        // Must NOT use the shared default pool socket
        $this->assertStringNotContainsString('php8.3-fpm.sock;', $config);
    }

    /** @test */
    public function it_uses_container_network_socket_in_non_standalone_mode()
    {
        $user = User::factory()->make(['id' => 1, 'username' => 'alice']);
        $virtualHost = VirtualHost::factory()->make([
            'user_id'     => 1,
            'hostname'    => 'alice.example.com',
            'document_root' => '/var/www/alice.example.com',
            'php_version' => '8.2',
            'ssl_enabled' => false,
        ]);
        $virtualHost->setRelation('user', $user);

        $this->detectionService->shouldReceive('isStandalone')->andReturn(false);

        $config = $this->callProtected('generateNginxConfig', $virtualHost);

        // Container mode uses a network socket, not a unix socket
        $this->assertStringContainsString('php-versions-8-2:9000', $config);
        $this->assertStringNotContainsString('unix:', $config);
    }

    // ------------------------------------------------------------------
    // StandaloneServiceHelper PHP-FPM pool methods exist
    // ------------------------------------------------------------------

    /** @test */
    public function standalone_helper_has_php_fpm_pool_management_methods()
    {
        $helper = new StandaloneServiceHelper(
            Mockery::mock(DeploymentDetectionService::class)
        );

        $this->assertTrue(method_exists($helper, 'getPhpFpmPoolPath'));
        $this->assertTrue(method_exists($helper, 'getPhpFpmSocketPath'));
        $this->assertTrue(method_exists($helper, 'deployPhpFpmPool'));
        $this->assertTrue(method_exists($helper, 'removePhpFpmPool'));
    }

    /** @test */
    public function get_php_fpm_socket_path_returns_correct_format()
    {
        $helper = new StandaloneServiceHelper(
            Mockery::mock(DeploymentDetectionService::class)
        );

        $path = $helper->getPhpFpmSocketPath('alice', '8.3');

        $this->assertSame('/run/php/php8.3-fpm-alice.sock', $path);
    }

    // ------------------------------------------------------------------
    // Helper: call protected method via reflection
    // ------------------------------------------------------------------

    private function callProtected(string $method, ...$args): mixed
    {
        $ref = new \ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->service, ...$args);
    }
}
