<?php

namespace Tests\Unit\Services\ManagedDatabase;

use App\Models\Database;
use App\Models\Domain;
use App\Models\User;
use App\Services\ManagedDatabase\ManagedDatabaseManager;
use App\Services\ManagedDatabase\AwsRdsProvider;
use App\Services\ManagedDatabase\AzureDatabaseProvider;
use App\Services\ManagedDatabase\DigitalOceanDatabaseProvider;
use App\Services\ManagedDatabase\OvhDatabaseProvider;
use App\Services\ManagedDatabase\GoogleCloudSqlProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedDatabaseManagerTest extends TestCase
{
    use RefreshDatabase;

    protected ManagedDatabaseManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ManagedDatabaseManager::class);
    }

    public function test_can_get_all_providers()
    {
        $providers = $this->manager->getProviders();

        $this->assertIsArray($providers);
        $this->assertCount(5, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_AWS, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_AZURE, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_DIGITALOCEAN, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_OVH, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_GCP, $providers);
    }

    public function test_can_get_provider_by_name()
    {
        $provider = $this->manager->getProviderByName(Database::PROVIDER_AWS);

        $this->assertNotNull($provider);
        $this->assertInstanceOf(AwsRdsProvider::class, $provider);
        $this->assertEquals('aws', $provider->getName());
    }

    public function test_returns_null_for_invalid_provider()
    {
        $provider = $this->manager->getProviderByName('invalid');

        $this->assertNull($provider);
    }

    public function test_can_check_if_provider_is_supported()
    {
        $this->assertTrue($this->manager->isProviderSupported(Database::PROVIDER_AWS));
        $this->assertTrue($this->manager->isProviderSupported(Database::PROVIDER_AZURE));
        $this->assertFalse($this->manager->isProviderSupported('invalid'));
    }

    public function test_can_get_available_instance_types_for_aws()
    {
        $instanceTypes = $this->manager->getAvailableInstanceTypes(Database::PROVIDER_AWS);

        $this->assertIsArray($instanceTypes);
        $this->assertNotEmpty($instanceTypes);
        $this->assertArrayHasKey('db.t3.micro', $instanceTypes);
    }

    public function test_can_get_available_regions_for_digitalocean()
    {
        $regions = $this->manager->getAvailableRegions(Database::PROVIDER_DIGITALOCEAN);

        $this->assertIsArray($regions);
        $this->assertNotEmpty($regions);
        $this->assertArrayHasKey('nyc3', $regions);
    }

    public function test_all_providers_implement_interface()
    {
        foreach ($this->manager->getProviders() as $provider) {
            $this->assertInstanceOf(
                \App\Services\ManagedDatabase\ManagedDatabaseProviderInterface::class,
                $provider
            );
        }
    }

    public function test_all_providers_have_unique_names()
    {
        $names = [];
        foreach ($this->manager->getProviders() as $provider) {
            $name = $provider->getName();
            $this->assertNotContains($name, $names, "Provider name '{$name}' is not unique");
            $names[] = $name;
        }
    }

    public function test_all_providers_have_available_regions()
    {
        foreach ($this->manager->getProviders() as $providerName => $provider) {
            $regions = $this->manager->getAvailableRegions($providerName);
            $this->assertIsArray($regions);
            $this->assertNotEmpty($regions, "Provider {$providerName} should have available regions");
        }
    }

    public function test_all_providers_have_available_instance_types()
    {
        foreach ($this->manager->getProviders() as $providerName => $provider) {
            $instanceTypes = $this->manager->getAvailableInstanceTypes($providerName);
            $this->assertIsArray($instanceTypes);
            $this->assertNotEmpty($instanceTypes, "Provider {$providerName} should have available instance types");
        }
    }
}
