<?php

namespace Tests\Unit\Services;

use App\Models\Domain;
use App\Models\Server;
use App\Models\User;
use App\Services\DeploymentAwareService;
use App\Services\DeploymentDetectionService;
use App\Services\KubernetesService;
use App\Services\DockerComposeService;
use App\Services\WebServerService;
use App\Services\SslService;
use App\Services\DatabaseService;
use App\Services\StandaloneServiceHelper;
use Tests\TestCase;
use Mockery;

class DeploymentAwareServiceTest extends TestCase
{
    protected DeploymentAwareService $service;
    protected $detectionService;
    protected $kubernetesService;
    protected $dockerService;
    protected $webServerService;
    protected $sslService;
    protected $databaseService;
    protected $standaloneHelper;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->detectionService = Mockery::mock(DeploymentDetectionService::class);
        $this->kubernetesService = Mockery::mock(KubernetesService::class);
        $this->dockerService = Mockery::mock(DockerComposeService::class);
        $this->webServerService = Mockery::mock(WebServerService::class);
        $this->sslService = Mockery::mock(SslService::class);
        $this->databaseService = Mockery::mock(DatabaseService::class);
        $this->standaloneHelper = Mockery::mock(StandaloneServiceHelper::class);
        
        $this->service = new DeploymentAwareService(
            $this->detectionService,
            $this->kubernetesService,
            $this->dockerService,
            $this->webServerService,
            $this->sslService,
            $this->databaseService,
            $this->standaloneHelper
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_deploys_to_kubernetes_when_server_type_is_kubernetes()
    {
        $user = User::factory()->make(['id' => 1]);
        $server = Server::factory()->make([
            'id' => 1,
            'type' => Server::TYPE_KUBERNETES,
            'name' => 'k8s-cluster'
        ]);
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'test.com',
            'user_id' => $user->id,
            'server_id' => $server->id
        ]);
        $domain->setRelation('server', $server);
        $domain->setRelation('user', $user);

        $this->kubernetesService->shouldReceive('deployDomain')
            ->once()
            ->with($domain, [])
            ->andReturn(true);

        $result = $this->service->deployDomain($domain);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_deploys_to_docker_when_server_type_is_docker()
    {
        $user = User::factory()->make(['id' => 1]);
        $server = Server::factory()->make([
            'id' => 1,
            'type' => Server::TYPE_DOCKER,
            'name' => 'docker-host'
        ]);
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'test.com',
            'user_id' => $user->id,
            'server_id' => $server->id
        ]);
        $domain->setRelation('server', $server);
        $domain->setRelation('user', $user);
        $domain->setRelation('hostingPlan', null);

        $this->dockerService->shouldReceive('generateComposeFile')
            ->once()
            ->andReturn(true);
        
        $this->dockerService->shouldReceive('startServices')
            ->once()
            ->andReturn(true);

        $result = $this->service->deployDomain($domain);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_deploys_to_standalone_when_server_type_is_standalone()
    {
        $user = User::factory()->make([
            'id' => 1,
            'email' => 'test@example.com'
        ]);
        $server = Server::factory()->make([
            'id' => 1,
            'type' => Server::TYPE_STANDALONE,
            'name' => 'standalone-server'
        ]);
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'test.com',
            'user_id' => $user->id,
            'server_id' => $server->id
        ]);
        $domain->setRelation('server', $server);
        $domain->setRelation('user', $user);

        // Mock the service calls
        $this->webServerService->shouldReceive('createNginxConfig')
            ->times(2) // Called twice: once without SSL, once with SSL
            ->andReturn('nginx config content');
        
        $this->webServerService->shouldReceive('testNginxConfig')
            ->once()
            ->andReturn(['success' => true]);
        
        $this->webServerService->shouldReceive('reloadNginx')
            ->times(2)
            ->andReturn(true);
        
        $this->standaloneHelper->shouldReceive('isCertbotInstalled')
            ->once()
            ->andReturn(true);
        
        $this->sslService->shouldReceive('generateLetsEncryptCertificate')
            ->once()
            ->andReturn(Mockery::mock('App\Models\SslCertificate'));

        $result = $this->service->deployDomain($domain, [
            'enable_ssl' => true
        ]);
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_deletes_standalone_deployment_correctly()
    {
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'test.com'
        ]);
        $server = Server::factory()->make([
            'id' => 1,
            'type' => Server::TYPE_STANDALONE
        ]);
        $domain->setRelation('server', $server);

        $this->standaloneHelper->shouldReceive('removeNginxConfig')
            ->once()
            ->with('test.com')
            ->andReturn(true);
        
        $this->standaloneHelper->shouldReceive('reloadSystemdService')
            ->once()
            ->with('nginx')
            ->andReturn(true);

        $result = $this->service->deleteDomain($domain);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_gets_standalone_deployment_status()
    {
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'test.com'
        ]);
        $server = Server::factory()->make([
            'id' => 1,
            'type' => Server::TYPE_STANDALONE
        ]);
        $domain->setRelation('server', $server);

        $this->standaloneHelper->shouldReceive('nginxConfigExists')
            ->once()
            ->with('test.com')
            ->andReturn(true);
        
        $this->standaloneHelper->shouldReceive('isSystemdServiceRunning')
            ->once()
            ->with('nginx')
            ->andReturn(true);
        
        $this->standaloneHelper->shouldReceive('certificateExists')
            ->once()
            ->with('test.com')
            ->andReturn(true);

        $status = $this->service->getDeploymentStatus($domain);
        
        $this->assertIsArray($status);
        $this->assertEquals('running', $status['status']);
        $this->assertTrue($status['details']['nginx_config_exists']);
        $this->assertTrue($status['details']['nginx_running']);
        $this->assertTrue($status['details']['ssl_enabled']);
    }

    /** @test */
    public function it_restarts_standalone_deployment_with_validation()
    {
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'test.com'
        ]);
        $server = Server::factory()->make([
            'id' => 1,
            'type' => Server::TYPE_STANDALONE
        ]);
        $domain->setRelation('server', $server);

        $this->webServerService->shouldReceive('testNginxConfig')
            ->once()
            ->andReturn(['success' => true]);
        
        $this->standaloneHelper->shouldReceive('reloadSystemdService')
            ->once()
            ->with('nginx')
            ->andReturn(true);

        $result = $this->service->restartDomain($domain);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_does_not_restart_if_nginx_config_test_fails()
    {
        $domain = Domain::factory()->make([
            'id' => 1,
            'domain_name' => 'test.com'
        ]);
        $server = Server::factory()->make([
            'id' => 1,
            'type' => Server::TYPE_STANDALONE
        ]);
        $domain->setRelation('server', $server);

        $this->webServerService->shouldReceive('testNginxConfig')
            ->once()
            ->andReturn(['success' => false, 'error' => 'Config error']);
        
        // Should NOT call reload if test fails
        $this->standaloneHelper->shouldNotReceive('reloadSystemdService');

        $result = $this->service->restartDomain($domain);
        $this->assertFalse($result);
    }
}
