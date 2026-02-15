<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Domain;
use App\Models\Database;
use App\Models\Server;
use App\Models\WordPressApplication;
use App\Services\WordPressService;
use App\Services\SshConnectionService;
use Illuminate\Support\Facades\Http;

class WordPressDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_latest_wordpress_version()
    {
        Http::fake([
            'https://api.wordpress.org/core/version-check/1.7/' => Http::response([
                'offers' => [
                    ['version' => '6.4.2']
                ]
            ], 200),
        ]);

        $sshService = $this->createMock(SshConnectionService::class);
        $wpService = new WordPressService($sshService);

        $version = $wpService->getLatestVersion();

        $this->assertEquals('6.4.2', $version);
    }

    public function test_wordpress_application_status_methods()
    {
        $wp = new WordPressApplication([
            'status' => 'installed',
        ]);

        $this->assertTrue($wp->isInstalled());
        $this->assertFalse($wp->isInstalling());
        $this->assertFalse($wp->hasFailed());
    }

    public function test_wordpress_application_relationships()
    {
        $domain = Domain::factory()->create();
        $database = Database::factory()->create();

        $wp = WordPressApplication::factory()->create([
            'domain_id' => $domain->id,
            'database_id' => $database->id,
        ]);

        $this->assertEquals($domain->id, $wp->domain->id);
        $this->assertEquals($database->id, $wp->database->id);
    }

    public function test_generate_wp_config()
    {
        $sshService = $this->createMock(SshConnectionService::class);
        $wpService = new WordPressService($sshService);

        $reflection = new \ReflectionClass($wpService);
        $method = $reflection->getMethod('generateWpConfig');
        $method->setAccessible(true);

        $config = $method->invoke(
            $wpService,
            'test_db',
            'test_user',
            'test_pass',
            'localhost',
            'https://example.com'
        );

        $this->assertStringContainsString("define('DB_NAME', 'test_db')", $config);
        $this->assertStringContainsString("define('DB_USER', 'test_user')", $config);
        $this->assertStringContainsString("define('DB_PASSWORD', 'test_pass')", $config);
        $this->assertStringContainsString("define('DB_HOST', 'localhost')", $config);
        $this->assertStringContainsString("define('WP_HOME', 'https://example.com')", $config);
        $this->assertStringContainsString("define('AUTH_KEY'", $config);
    }

    public function test_wordpress_application_full_path_attribute()
    {
        $wp = new WordPressApplication([
            'install_path' => '/public_html/',
        ]);

        $this->assertEquals('/public_html', $wp->full_path);
    }
}
