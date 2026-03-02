<?php

namespace Tests\Unit\Services;

use App\Services\DeploymentDetectionService;
use App\Services\JailkitService;
use App\Services\StandaloneServiceHelper;
use Tests\TestCase;
use Mockery;

class JailkitServiceTest extends TestCase
{
    protected JailkitService $jailkit;
    protected $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper  = Mockery::mock(StandaloneServiceHelper::class);
        $this->jailkit = new JailkitService($this->helper);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Path helpers
    // ------------------------------------------------------------------

    /** @test */
    public function get_document_root_returns_home_based_path()
    {
        $this->assertSame('/home/alice/public_html', $this->jailkit->getDocumentRoot('alice'));
        $this->assertSame('/home/cp-user-bob/public_html', $this->jailkit->getDocumentRoot('cp-user-bob'));
    }

    /** @test */
    public function get_jail_root_returns_home_directory()
    {
        $this->assertSame('/home/alice', $this->jailkit->getJailRoot('alice'));
        $this->assertSame('/home/cp-user-bob', $this->jailkit->getJailRoot('cp-user-bob'));
    }

    // ------------------------------------------------------------------
    // isInstalled
    // ------------------------------------------------------------------

    /** @test */
    public function is_installed_returns_true_when_jk_init_found()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => true, 'output' => '/usr/sbin/jk_init', 'error' => '', 'exit_code' => 0]);

        $this->assertTrue($this->jailkit->isInstalled());
    }

    /** @test */
    public function is_installed_returns_false_when_jk_init_not_found()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => false, 'output' => '', 'error' => '', 'exit_code' => 1]);

        $this->assertFalse($this->jailkit->isInstalled());
    }

    /** @test */
    public function is_installed_returns_false_when_output_is_empty()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['which', 'jk_init'])
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $this->assertFalse($this->jailkit->isInstalled());
    }

    // ------------------------------------------------------------------
    // initJail
    // ------------------------------------------------------------------

    /** @test */
    public function init_jail_runs_jk_init_with_correct_arguments()
    {
        $jailRoot = '/home/alice';

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'mkdir', '-p', $jailRoot])
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'chown', 'root:root', $jailRoot])
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'chmod', '0755', $jailRoot])
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'jk_init', '-v', '-j', $jailRoot, 'basicshell', 'jk_lsh', 'sftp', 'scp'], 120)
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $result = $this->jailkit->initJail($jailRoot);

        $this->assertTrue($result);
    }

    /** @test */
    public function init_jail_returns_false_when_jk_init_fails()
    {
        $jailRoot = '/home/alice';

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'mkdir', '-p', $jailRoot])
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'chown', 'root:root', $jailRoot])
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'chmod', '0755', $jailRoot])
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'jk_init', '-v', '-j', $jailRoot, 'basicshell', 'jk_lsh', 'sftp', 'scp'], 120)
            ->andReturn(['success' => false, 'output' => '', 'error' => 'jk_init error', 'exit_code' => 1]);

        $result = $this->jailkit->initJail($jailRoot);

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // removeUserJail
    // ------------------------------------------------------------------

    /** @test */
    public function remove_user_jail_resets_user_shell_to_nologin()
    {
        $this->helper->shouldReceive('executeCommand')
            ->withArgs(function (array $cmd) {
                return $cmd[0] === 'sudo'
                    && $cmd[1] === 'usermod'
                    && $cmd[2] === '-s'
                    && in_array($cmd[3], ['/usr/sbin/nologin', '/sbin/nologin'])
                    && $cmd[4] === 'alice';
            })
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $result = $this->jailkit->removeUserJail('alice');

        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // removeUserHomeDirectory
    // ------------------------------------------------------------------

    /** @test */
    public function remove_user_home_directory_deletes_home_path()
    {
        $this->helper->shouldReceive('executeCommand')
            ->with(['sudo', 'rm', '-rf', '/home/alice'])
            ->once()
            ->andReturn(['success' => true, 'output' => '', 'error' => '', 'exit_code' => 0]);

        $result = $this->jailkit->removeUserHomeDirectory('alice');

        $this->assertTrue($result);
    }
}
