<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PhpConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhpConfigTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user   = User::factory()->create();
        $this->domain = Domain::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_php_config_can_be_created(): void
    {
        $config = PhpConfig::factory()->create([
            'domain_id'   => $this->domain->id,
            'php_version' => '8.2',
            'memory_limit' => 256,
        ]);

        $this->assertDatabaseHas('php_configs', [
            'domain_id'    => $this->domain->id,
            'php_version'  => '8.2',
            'memory_limit' => 256,
        ]);
    }

    public function test_php_config_belongs_to_domain(): void
    {
        $config = PhpConfig::factory()->create(['domain_id' => $this->domain->id]);

        $this->assertTrue($config->domain->is($this->domain));
    }

    public function test_domain_has_php_config_relationship(): void
    {
        PhpConfig::factory()->create(['domain_id' => $this->domain->id]);

        $this->assertInstanceOf(PhpConfig::class, $this->domain->phpConfig);
    }

    public function test_to_ini_directives_generates_correct_values(): void
    {
        $config = PhpConfig::factory()->make([
            'memory_limit'        => 256,
            'upload_max_filesize' => 128,
            'post_max_size'       => 128,
            'max_execution_time'  => 120,
            'display_errors'      => false,
            'short_open_tag'      => false,
        ]);

        $directives = $config->toIniDirectives();

        $this->assertSame('256M', $directives['memory_limit']);
        $this->assertSame('128M', $directives['upload_max_filesize']);
        $this->assertSame('128M', $directives['post_max_size']);
        $this->assertSame(120, $directives['max_execution_time']);
        $this->assertSame('Off', $directives['display_errors']);
        $this->assertSame('Off', $directives['short_open_tag']);
    }

    public function test_to_ini_directives_includes_display_errors_on(): void
    {
        $config = PhpConfig::factory()->make(['display_errors' => true]);

        $this->assertSame('On', $config->toIniDirectives()['display_errors']);
    }

    public function test_to_ini_string_contains_domain_comment(): void
    {
        $config = PhpConfig::factory()->create(['domain_id' => $this->domain->id]);

        $ini = $config->toIniString();

        $this->assertStringContainsString($this->domain->domain_name, $ini);
        $this->assertStringContainsString('memory_limit', $ini);
    }

    public function test_custom_settings_are_included_in_directives(): void
    {
        $config = PhpConfig::factory()->make([
            'custom_settings' => ['opcache.enable' => '1', 'opcache.memory_consumption' => '128'],
        ]);

        $directives = $config->toIniDirectives();

        $this->assertArrayHasKey('opcache.enable', $directives);
        $this->assertArrayHasKey('opcache.memory_consumption', $directives);
    }

    public function test_domain_is_unique_per_php_config(): void
    {
        PhpConfig::factory()->create(['domain_id' => $this->domain->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PhpConfig::factory()->create(['domain_id' => $this->domain->id]);
    }

    public function test_supported_versions_returns_expected_list(): void
    {
        $versions = PhpConfig::getSupportedVersions();

        $this->assertContains('8.2', $versions);
        $this->assertContains('8.3', $versions);
        $this->assertNotEmpty($versions);
    }
}
