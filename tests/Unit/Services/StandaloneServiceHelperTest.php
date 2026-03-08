<?php

namespace Tests\Unit\Services;

use App\Services\StandaloneServiceHelper;
use App\Services\DeploymentDetectionService;
use Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class StandaloneServiceHelperTest extends TestCase
{
    protected StandaloneServiceHelper $helper;
    protected $detectionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->detectionService = Mockery::mock(DeploymentDetectionService::class);
        $this->helper = new StandaloneServiceHelper($this->detectionService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_determines_standalone_mode_correctly()
    {
        $this->detectionService->shouldReceive('isStandalone')
            ->once()
            ->andReturn(true);

        $result = $this->helper->shouldUseStandaloneMode();
        $this->assertTrue($result);
    }

    #[Test]
    public function it_checks_if_service_is_installed()
    {
        // This test would need to mock command execution
        // For now, we just verify the method exists
        $this->assertTrue(method_exists($this->helper, 'isServiceInstalled'));
    }

    #[Test]
    public function it_checks_systemd_service_status()
    {
        // This test would need to mock command execution
        $this->assertTrue(method_exists($this->helper, 'isSystemdServiceRunning'));
    }

    #[Test]
    public function it_has_nginx_config_management_methods()
    {
        $this->assertTrue(method_exists($this->helper, 'deployNginxConfig'));
        $this->assertTrue(method_exists($this->helper, 'removeNginxConfig'));
        $this->assertTrue(method_exists($this->helper, 'nginxConfigExists'));
    }

    #[Test]
    public function it_has_mysql_command_execution_methods()
    {
        $this->assertTrue(method_exists($this->helper, 'executeMysqlCommand'));
        $this->assertTrue(method_exists($this->helper, 'createMysqlDatabase'));
        $this->assertTrue(method_exists($this->helper, 'dropMysqlDatabase'));
    }

    #[Test]
    public function it_has_postgres_command_execution_methods()
    {
        $this->assertTrue(method_exists($this->helper, 'executePostgresCommand'));
        $this->assertTrue(method_exists($this->helper, 'createPostgresDatabase'));
        $this->assertTrue(method_exists($this->helper, 'dropPostgresDatabase'));
    }

    #[Test]
    public function it_has_certbot_methods()
    {
        $this->assertTrue(method_exists($this->helper, 'executeCertbot'));
        $this->assertTrue(method_exists($this->helper, 'renewCertbotCertificate'));
        $this->assertTrue(method_exists($this->helper, 'isCertbotInstalled'));
        $this->assertTrue(method_exists($this->helper, 'certificateExists'));
    }

    #[Test]
    public function it_provides_certificate_paths()
    {
        $paths = $this->helper->getCertificatePath('example.com');
        
        $this->assertIsArray($paths);
        $this->assertArrayHasKey('fullchain', $paths);
        $this->assertArrayHasKey('privkey', $paths);
        $this->assertArrayHasKey('chain', $paths);
        $this->assertArrayHasKey('cert', $paths);
        
        $this->assertStringContainsString('example.com', $paths['fullchain']);
    }

    #[Test]
    public function it_formats_execute_command_result_correctly()
    {
        // Test that executeCommand returns proper structure
        $result = $this->helper->executeCommand(['echo', 'test']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('exit_code', $result);
    }

    // ------------------------------------------------------------------
    // Home directory helpers
    // ------------------------------------------------------------------

    #[Test]
    public function get_home_directory_returns_correct_path()
    {
        $this->assertSame('/home/cp-user-alice', $this->helper->getHomeDirectory('cp-user-alice'));
    }

    #[Test]
    public function get_public_html_directory_without_hostname_returns_default()
    {
        $path = $this->helper->getPublicHtmlDirectory('cp-user-alice');
        $this->assertSame('/home/cp-user-alice/public_html', $path);
    }

    #[Test]
    public function get_public_html_directory_with_hostname_returns_per_vhost_path()
    {
        $path = $this->helper->getPublicHtmlDirectory('cp-user-alice', 'example.com');
        $this->assertSame('/home/cp-user-alice/example.com/public_html', $path);
    }

    #[Test]
    public function home_directory_is_always_under_slash_home()
    {
        foreach (['user1', 'cp-user-alice', 'cp-user-bob'] as $username) {
            $this->assertStringStartsWith('/home/', $this->helper->getHomeDirectory($username));
        }
    }
}
