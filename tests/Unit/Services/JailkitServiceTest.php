<?php

namespace Tests\Unit\Services;

use App\Services\JailkitService;
use App\Services\StandaloneServiceHelper;
use App\Services\DeploymentDetectionService;
use Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class JailkitServiceTest extends TestCase
{
    protected JailkitService $service;
    protected $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper  = Mockery::mock(StandaloneServiceHelper::class);
        $this->service = new JailkitService($this->helper);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // isInstalled
    // ------------------------------------------------------------------

    #[Test]
    public function it_returns_true_when_jailkit_is_installed()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => true, 'output' => '/usr/sbin/jk_init', 'error' => '']);

        $this->assertTrue($this->service->isInstalled());
    }

    #[Test]
    public function it_returns_false_when_jailkit_is_not_installed()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => false, 'output' => '', 'error' => '']);

        $this->assertFalse($this->service->isInstalled());
    }

    // ------------------------------------------------------------------
    // initJail
    // ------------------------------------------------------------------

    #[Test]
    public function it_returns_false_when_jailkit_not_installed_on_init()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => false, 'output' => '', 'error' => '']);

        $result = $this->service->initJail('/home/alice/jail');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_calls_jk_init_with_correct_arguments()
    {
        $jailPath = '/home/alice/jail';
        $sections = ['basicshell', 'sftp'];

        // First call: isInstalled check
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => true, 'output' => '/usr/sbin/jk_init', 'error' => '']);

        // Second call: jk_init
        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'jk_init', '-v', '-j', $jailPath, 'basicshell', 'sftp'], 120)
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '']);

        $result = $this->service->initJail($jailPath, $sections);

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // jailUser
    // ------------------------------------------------------------------

    #[Test]
    public function it_calls_jk_jailuser_with_correct_arguments()
    {
        $username = 'cp-user-alice';
        $jailPath = '/home/cp-user-alice/jail';

        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => true, 'output' => '/usr/sbin/jk_init', 'error' => '']);

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'jk_jailuser', '-v', '-j', $jailPath, '-s', '/bin/bash', $username], 60)
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '']);

        $result = $this->service->jailUser($username, $jailPath);

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // removeJail – safety checks
    // ------------------------------------------------------------------

    #[Test]
    public function it_refuses_to_remove_protected_paths()
    {
        foreach (['/', '/home', '/etc', '/var', '/usr', '/bin', '/sbin', '/tmp'] as $path) {
            $this->assertFalse($this->service->removeJail($path), "Expected false for path: {$path}");
        }
    }

    #[Test]
    public function it_removes_a_valid_jail_path()
    {
        $jailPath = '/home/cp-user-alice/jail';

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'rm', '-rf', $jailPath], 60)
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '']);

        $this->assertTrue($this->service->removeJail($jailPath));
    }

    // ------------------------------------------------------------------
    // setupUserJail – convenience method
    // ------------------------------------------------------------------

    #[Test]
    public function setup_user_jail_returns_failure_when_jailkit_not_installed()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => false, 'output' => '', 'error' => '']);

        $result = $this->service->setupUserJail('cp-user-alice');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not installed', $result['message']);
    }

    #[Test]
    public function setup_user_jail_returns_correct_jail_path()
    {
        $username = 'cp-user-alice';
        $jailPath = "/home/{$username}/jail";

        // isInstalled (x2: once in setupUserJail, once inside initJail, once inside jailUser)
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->times(3)
            ->andReturn(['success' => true, 'output' => '/usr/sbin/jk_init', 'error' => '']);

        // jk_init
        $this->helper->shouldReceive('executeCommand')
            ->with(Mockery::on(fn ($cmd) => ($cmd[1] ?? '') === 'jk_init'), 120)
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '']);

        // jk_jailuser
        $this->helper->shouldReceive('executeCommand')
            ->with(Mockery::on(fn ($cmd) => ($cmd[1] ?? '') === 'jk_jailuser'), 60)
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '']);

        $result = $this->service->setupUserJail($username);

        $this->assertTrue($result['success']);
        $this->assertSame($jailPath, $result['jail_path']);
    }

    // ------------------------------------------------------------------
    // DEFAULT_SECTIONS constant
    // ------------------------------------------------------------------

    #[Test]
    public function default_sections_include_sftp()
    {
        $this->assertContains('sftp', JailkitService::DEFAULT_SECTIONS);
        $this->assertContains('basicshell', JailkitService::DEFAULT_SECTIONS);
    }
}
