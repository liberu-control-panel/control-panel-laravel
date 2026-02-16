<?php

namespace Tests\Unit\Services;

use App\Services\StandaloneServiceChecker;
use App\Services\StandaloneServiceHelper;
use App\Services\DeploymentDetectionService;
use Tests\TestCase;
use Mockery;

class StandaloneServiceCheckerTest extends TestCase
{
    protected StandaloneServiceChecker $checker;
    protected $helper;
    protected $detectionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->helper = Mockery::mock(StandaloneServiceHelper::class);
        $this->detectionService = Mockery::mock(DeploymentDetectionService::class);
        $this->checker = new StandaloneServiceChecker($this->helper, $this->detectionService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_detects_standalone_mode()
    {
        $this->detectionService->shouldReceive('isStandalone')
            ->once()
            ->andReturn(true);

        $result = $this->checker->isStandaloneMode();
        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_not_standalone_mode_when_not_standalone()
    {
        $this->detectionService->shouldReceive('isStandalone')
            ->once()
            ->andReturn(false);

        $result = $this->checker->checkAllServices();
        
        $this->assertIsArray($result);
        $this->assertFalse($result['standalone_mode']);
    }

    /** @test */
    public function it_checks_all_services_in_standalone_mode()
    {
        $this->detectionService->shouldReceive('isStandalone')
            ->andReturn(true);

        // Mock helper methods
        $this->helper->shouldReceive('isServiceInstalled')->andReturn(true);
        $this->helper->shouldReceive('isSystemdServiceRunning')->andReturn(true);
        $this->helper->shouldReceive('isCertbotInstalled')->andReturn(true);

        $result = $this->checker->checkAllServices();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['standalone_mode']);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('all_services_ready', $result);
    }

    /** @test */
    public function it_checks_nginx_service()
    {
        $this->helper->shouldReceive('isServiceInstalled')
            ->with('nginx')
            ->once()
            ->andReturn(true);

        $this->helper->shouldReceive('isSystemdServiceRunning')
            ->with('nginx')
            ->once()
            ->andReturn(true);

        $result = $this->checker->checkNginx();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['installed']);
        $this->assertTrue($result['running']);
        $this->assertEquals('nginx', $result['service_name']);
        $this->assertEquals('ready', $result['status']);
    }

    /** @test */
    public function it_checks_php_fpm_service()
    {
        $this->helper->shouldReceive('isSystemdServiceRunning')
            ->andReturn(true);

        $result = $this->checker->checkPhpFpm();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('installed', $result);
        $this->assertArrayHasKey('running', $result);
        $this->assertArrayHasKey('available_versions', $result);
    }

    /** @test */
    public function it_checks_mysql_service()
    {
        $this->helper->shouldReceive('isServiceInstalled')
            ->andReturn(true);

        $this->helper->shouldReceive('isSystemdServiceRunning')
            ->andReturn(true);

        $result = $this->checker->checkMysql();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['installed']);
        $this->assertTrue($result['running']);
        $this->assertEquals('ready', $result['status']);
    }

    /** @test */
    public function it_returns_not_installed_status_when_service_not_installed()
    {
        $this->helper->shouldReceive('isServiceInstalled')
            ->with('nginx')
            ->once()
            ->andReturn(false);

        $result = $this->checker->checkNginx();
        
        $this->assertFalse($result['installed']);
        $this->assertFalse($result['running']);
        $this->assertEquals('not_installed', $result['status']);
    }

    /** @test */
    public function it_returns_not_running_status_when_service_not_running()
    {
        $this->helper->shouldReceive('isServiceInstalled')
            ->with('nginx')
            ->once()
            ->andReturn(true);

        $this->helper->shouldReceive('isSystemdServiceRunning')
            ->with('nginx')
            ->once()
            ->andReturn(false);

        $result = $this->checker->checkNginx();
        
        $this->assertTrue($result['installed']);
        $this->assertFalse($result['running']);
        $this->assertEquals('not_running', $result['status']);
    }

    /** @test */
    public function it_has_check_methods_for_all_services()
    {
        $methods = [
            'checkNginx',
            'checkPhpFpm',
            'checkMysql',
            'checkPostgresql',
            'checkPostfix',
            'checkDovecot',
            'checkBind9',
            'checkCertbot',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->checker, $method),
                "Method {$method} does not exist"
            );
        }
    }

    /** @test */
    public function it_provides_installation_commands()
    {
        $this->detectionService->shouldReceive('isStandalone')
            ->andReturn(true);

        // Mock all services as not installed
        $this->helper->shouldReceive('isServiceInstalled')->andReturn(false);
        $this->helper->shouldReceive('isSystemdServiceRunning')->andReturn(false);
        $this->helper->shouldReceive('isCertbotInstalled')->andReturn(false);
        $this->helper->shouldReceive('executeCommand')->andReturn([
            'success' => true,
            'output' => 'ID=ubuntu' . "\n" . 'VERSION_ID=22.04'
        ]);

        $commands = $this->checker->getInstallationCommands();
        
        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);
    }
}
