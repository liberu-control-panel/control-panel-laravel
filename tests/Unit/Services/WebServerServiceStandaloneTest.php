<?php

namespace Tests\Unit\Services;

use App\Models\Domain;
use App\Models\User;
use App\Services\WebServerService;
use App\Services\ContainerManagerService;
use App\Services\StandaloneServiceHelper;
use App\Services\DeploymentDetectionService;
use Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class WebServerServiceStandaloneTest extends TestCase
{
    protected WebServerService $service;
    protected $containerManager;
    protected $standaloneHelper;
    protected $detectionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->containerManager = Mockery::mock(ContainerManagerService::class);
        $this->standaloneHelper = Mockery::mock(StandaloneServiceHelper::class);
        $this->detectionService = Mockery::mock(DeploymentDetectionService::class);
        
        $this->service = new WebServerService(
            $this->containerManager,
            $this->standaloneHelper,
            $this->detectionService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_nginx_config_with_standalone_ssl_paths()
    {
        $user = User::factory()->make(['id' => 1]);
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'example.com',
            'user_id' => $user->id,
            'server_id' => 1,
        ]);

        $this->detectionService->shouldReceive('isStandalone')
            ->andReturn(true);

        $this->standaloneHelper->shouldReceive('getPublicHtmlDirectory')
            ->once()
            ->andReturn('/home/www-data/example.com/public_html');

        $this->standaloneHelper->shouldReceive('deployNginxConfig')
            ->once()
            ->andReturn(true);

        $config = $this->service->createNginxConfig($domain, [
            'enable_ssl' => true,
            'php_version' => '8.2',
        ]);

        // Verify Let's Encrypt paths are used for standalone
        $this->assertStringContainsString('/etc/letsencrypt/live/example.com/fullchain.pem', $config);
        $this->assertStringContainsString('/etc/letsencrypt/live/example.com/privkey.pem', $config);
        
        // Verify Unix socket is used for PHP-FPM
        $this->assertStringContainsString('unix:/var/run/php/php8.2-fpm.sock', $config);
    }

    #[Test]
    public function it_generates_nginx_config_with_docker_paths()
    {
        $user = User::factory()->make(['id' => 1]);
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'example.com',
            'user_id' => $user->id,
            'server_id' => 1,
        ]);

        $this->detectionService->shouldReceive('isStandalone')
            ->andReturn(false);

        $config = $this->service->createNginxConfig($domain, [
            'enable_ssl' => true,
            'php_version' => '8.2',
        ]);

        // Verify Docker paths are used
        $this->assertStringContainsString('/etc/nginx/certs/example.com.crt', $config);
        $this->assertStringContainsString('/etc/nginx/certs/example.com.key', $config);
        
        // Verify container name is used for PHP-FPM
        $this->assertStringContainsString('example.com_php:9000', $config);
    }

    #[Test]
    public function it_reloads_nginx_in_standalone_mode()
    {
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'example.com',
            'user_id' => 1,
            'server_id' => 1,
        ]);

        $this->detectionService->shouldReceive('isStandalone')
            ->once()
            ->andReturn(true);

        $this->standaloneHelper->shouldReceive('reloadSystemdService')
            ->once()
            ->with('nginx')
            ->andReturn(true);

        $result = $this->service->reloadNginx($domain);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_tests_nginx_config_in_standalone_mode()
    {
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'example.com',
            'user_id' => 1,
            'server_id' => 1,
        ]);

        $this->detectionService->shouldReceive('isStandalone')
            ->once()
            ->andReturn(true);

        $this->standaloneHelper->shouldReceive('testNginxConfig')
            ->once()
            ->andReturn([
                'success' => true,
                'output' => 'nginx: configuration file /etc/nginx/nginx.conf test is successful',
                'error' => ''
            ]);

        $result = $this->service->testNginxConfig($domain);
        
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('successful', $result['output']);
    }

    #[Test]
    public function it_handles_nginx_config_test_failure_in_standalone_mode()
    {
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'example.com',
            'user_id' => 1,
            'server_id' => 1,
        ]);

        $this->detectionService->shouldReceive('isStandalone')
            ->once()
            ->andReturn(true);

        $this->standaloneHelper->shouldReceive('testNginxConfig')
            ->once()
            ->andReturn([
                'success' => false,
                'output' => '',
                'error' => 'nginx: [emerg] invalid parameter'
            ]);

        $result = $this->service->testNginxConfig($domain);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('invalid parameter', $result['error']);
    }

    #[Test]
    public function it_generates_config_without_ssl_in_standalone_mode()
    {
        $user = User::factory()->make(['id' => 1]);
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'example.com',
            'user_id' => $user->id,
            'server_id' => 1,
        ]);

        $this->detectionService->shouldReceive('isStandalone')
            ->andReturn(true);

        $this->standaloneHelper->shouldReceive('getPublicHtmlDirectory')
            ->once()
            ->andReturn('/home/www-data/example.com/public_html');

        $this->standaloneHelper->shouldReceive('deployNginxConfig')
            ->once()
            ->andReturn(true);

        $config = $this->service->createNginxConfig($domain, [
            'enable_ssl' => false,
            'php_version' => '8.2',
        ]);

        // Verify no SSL configuration
        $this->assertStringNotContainsString('ssl_certificate', $config);
        $this->assertStringNotContainsString('443', $config);
        $this->assertStringContainsString('listen 80', $config);
    }

    #[Test]
    public function it_deploys_nginx_config_to_system_directory_in_standalone_mode()
    {
        $user = User::factory()->make(['id' => 1]);
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'example.com',
            'user_id' => $user->id,
            'server_id' => 1,
        ]);

        $this->detectionService->shouldReceive('isStandalone')
            ->andReturn(true);

        $this->standaloneHelper->shouldReceive('getPublicHtmlDirectory')
            ->once()
            ->andReturn('/home/www-data/example.com/public_html');

        // Expect deployment to /etc/nginx/sites-available and sites-enabled
        $this->standaloneHelper->shouldReceive('deployNginxConfig')
            ->once()
            ->with('example.com', Mockery::type('string'))
            ->andReturn(true);

        $config = $this->service->createNginxConfig($domain);
        
        $this->assertNotEmpty($config);
    }
}
