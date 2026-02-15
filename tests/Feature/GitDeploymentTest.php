<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Domain;
use App\Models\GitDeployment;
use App\Models\Server;
use App\Services\GitDeploymentService;
use App\Services\SshConnectionService;

class GitDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_git_deployment_status_methods()
    {
        $deployment = new GitDeployment([
            'status' => 'deployed',
        ]);

        $this->assertTrue($deployment->isDeployed());
        $this->assertFalse($deployment->isDeploying());
        $this->assertFalse($deployment->hasFailed());
    }

    public function test_git_deployment_relationships()
    {
        $domain = Domain::factory()->create();

        $deployment = GitDeployment::factory()->create([
            'domain_id' => $domain->id,
        ]);

        $this->assertEquals($domain->id, $deployment->domain->id);
    }

    public function test_repository_type_detection()
    {
        $this->assertEquals('github', GitDeployment::detectRepositoryType('https://github.com/user/repo'));
        $this->assertEquals('gitlab', GitDeployment::detectRepositoryType('https://gitlab.com/user/repo'));
        $this->assertEquals('bitbucket', GitDeployment::detectRepositoryType('https://bitbucket.org/user/repo'));
        $this->assertEquals('other', GitDeployment::detectRepositoryType('https://example.com/user/repo'));
    }

    public function test_private_repository_detection()
    {
        $deployment = new GitDeployment([
            'deploy_key' => 'ssh-rsa AAAAB3...',
        ]);

        $this->assertTrue($deployment->isPrivate());

        $publicDeployment = new GitDeployment([
            'deploy_key' => null,
        ]);

        $this->assertFalse($publicDeployment->isPrivate());
    }

    public function test_repository_name_attribute()
    {
        $deployment = new GitDeployment([
            'repository_url' => 'https://github.com/user/my-repo.git',
        ]);

        $this->assertEquals('my-repo', $deployment->repository_name);
    }

    public function test_validate_repository_urls()
    {
        $sshService = $this->createMock(SshConnectionService::class);
        $service = new GitDeploymentService($sshService);

        // Valid URLs
        $this->assertTrue($service->isValidRepositoryUrl('https://github.com/user/repo.git'));
        $this->assertTrue($service->isValidRepositoryUrl('https://gitlab.com/user/repo'));
        $this->assertTrue($service->isValidRepositoryUrl('git@github.com:user/repo.git'));
        $this->assertTrue($service->isValidRepositoryUrl('ssh://git@example.com/repo.git'));

        // Invalid URLs
        $this->assertFalse($service->isValidRepositoryUrl('not-a-url'));
        $this->assertFalse($service->isValidRepositoryUrl('http://'));
    }

    public function test_webhook_secret_generation()
    {
        $sshService = $this->createMock(SshConnectionService::class);
        $service = new GitDeploymentService($sshService);

        $secret = $service->generateWebhookSecret();

        $this->assertIsString($secret);
        $this->assertEquals(40, strlen($secret));
    }

    public function test_github_webhook_validation()
    {
        $sshService = $this->createMock(SshConnectionService::class);
        $service = new GitDeploymentService($sshService);

        $secret = 'test_secret';
        $payload = '{"ref":"refs/heads/main"}';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($service->validateGitHubWebhook($payload, $signature, $secret));
        $this->assertFalse($service->validateGitHubWebhook($payload, 'invalid', $secret));
    }

    public function test_gitlab_webhook_validation()
    {
        $sshService = $this->createMock(SshConnectionService::class);
        $service = new GitDeploymentService($sshService);

        $secret = 'test_secret';

        $this->assertTrue($service->validateGitLabWebhook($secret, $secret));
        $this->assertFalse($service->validateGitLabWebhook('wrong', $secret));
    }

    public function test_full_path_attribute()
    {
        $deployment = new GitDeployment([
            'deploy_path' => '/var/www/site/',
        ]);

        $this->assertEquals('/var/www/site', $deployment->full_path);
    }
}
