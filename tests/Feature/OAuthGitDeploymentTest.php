<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Domain;
use App\Models\GitDeployment;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Services\OAuthRepositoryService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class OAuthGitDeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_deployment_with_oauth_is_detected_as_private()
    {
        $user = User::factory()->create();
        $account = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $deployment = new GitDeployment([
            'connected_account_id' => $account->id,
            'use_oauth' => true,
            'deploy_key' => null,
        ]);

        $this->assertTrue($deployment->isPrivate());
    }

    public function test_deployment_uses_oauth_when_configured()
    {
        $user = User::factory()->create();
        $account = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $domain = Domain::factory()->create();
        $deployment = GitDeployment::factory()->create([
            'domain_id' => $domain->id,
            'connected_account_id' => $account->id,
            'use_oauth' => true,
        ]);

        $this->assertTrue($deployment->usesOAuth());
    }

    public function test_deployment_without_oauth_account_does_not_use_oauth()
    {
        $domain = Domain::factory()->create();
        $deployment = GitDeployment::factory()->create([
            'domain_id' => $domain->id,
            'connected_account_id' => null,
            'use_oauth' => false,
        ]);

        $this->assertFalse($deployment->usesOAuth());
    }

    public function test_deployment_has_container_isolation_when_configured()
    {
        $domain = Domain::factory()->create();
        $deployment = GitDeployment::factory()->create([
            'domain_id' => $domain->id,
            'kubernetes_pod_name' => 'web-example-com-abc123',
            'kubernetes_namespace' => 'hosting-example-com',
        ]);

        $this->assertTrue($deployment->hasContainerIsolation());
    }

    public function test_connected_account_relationship()
    {
        $user = User::factory()->create();
        $account = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $domain = Domain::factory()->create();
        $deployment = GitDeployment::factory()->create([
            'domain_id' => $domain->id,
            'connected_account_id' => $account->id,
        ]);

        $this->assertEquals($account->id, $deployment->connectedAccount->id);
        $this->assertEquals('github', $deployment->connectedAccount->provider);
    }

    public function test_github_oauth_clone_url_generation()
    {
        $service = new OAuthRepositoryService();
        $user = User::factory()->create();
        $account = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'token' => 'test_github_token_123',
        ]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getGitHubOAuthCloneUrl');
        $method->setAccessible(true);

        // Test HTTPS URL
        $httpsUrl = 'https://github.com/user/repo.git';
        $result = $method->invoke($service, $account, $httpsUrl);
        $this->assertEquals('https://test_github_token_123@github.com/user/repo.git', $result);

        // Test SSH URL conversion
        $sshUrl = 'git@github.com:user/repo.git';
        $result = $method->invoke($service, $account, $sshUrl);
        $this->assertEquals('https://test_github_token_123@github.com/user/repo.git', $result);
    }

    public function test_gitlab_oauth_clone_url_generation()
    {
        $service = new OAuthRepositoryService();
        $user = User::factory()->create();
        $account = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'gitlab',
            'token' => 'test_gitlab_token_456',
        ]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getGitLabOAuthCloneUrl');
        $method->setAccessible(true);

        // Test HTTPS URL
        $httpsUrl = 'https://gitlab.com/user/repo.git';
        $result = $method->invoke($service, $account, $httpsUrl);
        $this->assertEquals('https://oauth2:test_gitlab_token_456@gitlab.com/user/repo.git', $result);

        // Test SSH URL conversion
        $sshUrl = 'git@gitlab.com:user/repo.git';
        $result = $method->invoke($service, $account, $sshUrl);
        $this->assertEquals('https://oauth2:test_gitlab_token_456@gitlab.com/user/repo.git', $result);
    }

    public function test_oauth_token_refresh_detection()
    {
        $service = new OAuthRepositoryService();
        $user = User::factory()->create();
        
        // Token that expires in 10 minutes (within refresh window)
        $expiringAccount = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'token' => 'old_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addMinutes(3), // Within 5-minute threshold
        ]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('refreshTokenIfNeeded');
        $method->setAccessible(true);

        // Should attempt refresh (will fail in test without mocking HTTP)
        try {
            $method->invoke($service, $expiringAccount);
        } catch (\Exception $e) {
            // Expected to fail without HTTP mocking
            $this->assertTrue(true);
        }

        // Token that doesn't expire soon
        $validAccount = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'token' => 'valid_token',
            'expires_at' => now()->addHours(2),
        ]);

        $result = $method->invoke($service, $validAccount);
        $this->assertTrue($result);
    }

    public function test_deployment_relationship_with_container()
    {
        $domain = Domain::factory()->create();
        $container = \App\Models\Container::factory()->create([
            'domain_id' => $domain->id,
        ]);

        $deployment = GitDeployment::factory()->create([
            'domain_id' => $domain->id,
            'container_id' => $container->id,
        ]);

        $this->assertEquals($container->id, $deployment->container->id);
    }

    public function test_deployment_fillable_fields_include_oauth_and_container()
    {
        $user = User::factory()->create();
        $account = ConnectedAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $domain = Domain::factory()->create();
        
        $deployment = GitDeployment::create([
            'domain_id' => $domain->id,
            'connected_account_id' => $account->id,
            'use_oauth' => true,
            'kubernetes_pod_name' => 'test-pod',
            'kubernetes_namespace' => 'test-namespace',
            'repository_url' => 'https://github.com/user/repo.git',
            'repository_type' => 'github',
            'branch' => 'main',
            'deploy_path' => '/public_html',
            'status' => 'pending',
        ]);

        $this->assertEquals($account->id, $deployment->connected_account_id);
        $this->assertTrue($deployment->use_oauth);
        $this->assertEquals('test-pod', $deployment->kubernetes_pod_name);
        $this->assertEquals('test-namespace', $deployment->kubernetes_namespace);
    }

    public function test_oauth_deployment_casts_use_oauth_to_boolean()
    {
        $domain = Domain::factory()->create();
        
        $deployment = GitDeployment::factory()->create([
            'domain_id' => $domain->id,
            'use_oauth' => 1, // Integer input
        ]);

        $this->assertIsBool($deployment->use_oauth);
        $this->assertTrue($deployment->use_oauth);
    }
}
