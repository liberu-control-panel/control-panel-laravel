<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Domain;
use App\Models\Database;
use App\Models\LaravelApplication;
use App\Services\LaravelApplicationService;

class LaravelApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_application_status_methods()
    {
        $app = new LaravelApplication([
            'status' => 'installed',
        ]);

        $this->assertTrue($app->isInstalled());
        $this->assertFalse($app->isInstalling());
        $this->assertFalse($app->hasFailed());
    }

    public function test_laravel_application_relationships()
    {
        $domain = Domain::factory()->create();
        $database = Database::factory()->create();

        $app = LaravelApplication::create([
            'domain_id' => $domain->id,
            'database_id' => $database->id,
            'repository_slug' => 'crm',
            'repository_name' => 'CRM',
            'repository_url' => 'liberu-crm/crm-laravel',
            'app_url' => 'https://crm.example.com',
            'install_path' => '/public_html',
            'php_version' => '8.2',
            'status' => 'pending',
        ]);

        $this->assertEquals($domain->id, $app->domain->id);
        $this->assertEquals($database->id, $app->database->id);
    }

    public function test_laravel_application_full_path_attribute()
    {
        $app = new LaravelApplication([
            'install_path' => '/public_html/',
        ]);

        $this->assertEquals('/public_html', $app->full_path);
    }

    public function test_laravel_application_github_url_attribute()
    {
        $app = new LaravelApplication([
            'repository_url' => 'liberu-accounting/accounting-laravel',
        ]);

        $this->assertEquals('https://github.com/liberu-accounting/accounting-laravel', $app->github_url);
    }

    public function test_get_available_repositories()
    {
        $service = app(LaravelApplicationService::class);
        $repositories = $service->getAvailableRepositories();

        $this->assertIsArray($repositories);
        $this->assertNotEmpty($repositories);
        
        // Check that each repository has required fields
        foreach ($repositories as $repo) {
            $this->assertArrayHasKey('name', $repo);
            $this->assertArrayHasKey('slug', $repo);
            $this->assertArrayHasKey('repository', $repo);
            $this->assertArrayHasKey('description', $repo);
        }
    }

    public function test_get_repository_by_slug()
    {
        $service = app(LaravelApplicationService::class);
        
        $repo = $service->getRepositoryBySlug('accounting');
        
        $this->assertNotNull($repo);
        $this->assertEquals('Accounting', $repo['name']);
        $this->assertEquals('liberu-accounting/accounting-laravel', $repo['repository']);
    }

    public function test_get_repository_by_invalid_slug_returns_null()
    {
        $service = app(LaravelApplicationService::class);
        
        $repo = $service->getRepositoryBySlug('invalid-slug');
        
        $this->assertNull($repo);
    }

    public function test_repository_config_attribute()
    {
        $app = new LaravelApplication([
            'repository_slug' => 'cms',
        ]);

        $config = $app->repository_config;

        $this->assertNotNull($config);
        $this->assertEquals('CMS', $config['name']);
        $this->assertEquals('liberu-cms/cms-laravel', $config['repository']);
    }

    public function test_all_configured_repositories_have_required_fields()
    {
        $repositories = config('repositories.repositories');

        foreach ($repositories as $repo) {
            $this->assertArrayHasKey('name', $repo, 'Repository missing name field');
            $this->assertArrayHasKey('slug', $repo, 'Repository missing slug field');
            $this->assertArrayHasKey('repository', $repo, 'Repository missing repository field');
            $this->assertArrayHasKey('description', $repo, 'Repository missing description field');
            $this->assertArrayHasKey('icon', $repo, 'Repository missing icon field');
            $this->assertArrayHasKey('category', $repo, 'Repository missing category field');
        }
    }

    public function test_configured_repositories_count()
    {
        $repositories = config('repositories.repositories');
        
        // Should have 13 repositories as per the issue
        $this->assertCount(13, $repositories);
    }

    public function test_all_repository_slugs_are_unique()
    {
        $repositories = config('repositories.repositories');
        $slugs = array_column($repositories, 'slug');
        $uniqueSlugs = array_unique($slugs);

        $this->assertEquals(count($slugs), count($uniqueSlugs), 'Duplicate slugs found in repository configuration');
    }
}
