<?php

namespace Tests\Unit\Models;

use App\Models\Database;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_can_be_created_as_self_hosted()
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['user_id' => $user->id]);

        $database = Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'test_db',
            'engine' => Database::ENGINE_MYSQL,
            'connection_type' => Database::CONNECTION_SELF_HOSTED,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $this->assertInstanceOf(Database::class, $database);
        $this->assertTrue($database->isSelfHosted());
        $this->assertFalse($database->isManaged());
        $this->assertEquals('test_db', $database->name);
    }

    public function test_database_can_be_created_as_managed()
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['user_id' => $user->id]);

        $database = Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'test_db',
            'engine' => Database::ENGINE_POSTGRESQL,
            'connection_type' => Database::CONNECTION_MANAGED,
            'provider' => Database::PROVIDER_AWS,
            'external_host' => 'test.rds.amazonaws.com',
            'external_port' => 5432,
            'external_username' => 'admin',
            'external_password' => 'secret',
            'use_ssl' => true,
            'region' => 'us-east-1',
        ]);

        $this->assertInstanceOf(Database::class, $database);
        $this->assertTrue($database->isManaged());
        $this->assertFalse($database->isSelfHosted());
        $this->assertEquals(Database::PROVIDER_AWS, $database->provider);
        $this->assertEquals('test.rds.amazonaws.com', $database->external_host);
        $this->assertTrue($database->use_ssl);
    }

    public function test_external_password_is_encrypted()
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['user_id' => $user->id]);

        $database = Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'test_db',
            'engine' => Database::ENGINE_MYSQL,
            'connection_type' => Database::CONNECTION_MANAGED,
            'provider' => Database::PROVIDER_DIGITALOCEAN,
            'external_password' => 'my_secret_password',
        ]);

        // Refresh from database
        $database->refresh();

        // The password should be encrypted in the database
        $rawPassword = \DB::table('databases')
            ->where('id', $database->id)
            ->value('external_password');

        $this->assertNotEquals('my_secret_password', $rawPassword);
        
        // But the model should decrypt it
        $this->assertEquals('my_secret_password', $database->external_password);
    }

    public function test_can_get_available_providers()
    {
        $providers = Database::getProviders();

        $this->assertIsArray($providers);
        $this->assertCount(5, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_AWS, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_AZURE, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_DIGITALOCEAN, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_OVH, $providers);
        $this->assertArrayHasKey(Database::PROVIDER_GCP, $providers);
    }

    public function test_can_get_connection_types()
    {
        $types = Database::getConnectionTypes();

        $this->assertIsArray($types);
        $this->assertCount(2, $types);
        $this->assertArrayHasKey(Database::CONNECTION_SELF_HOSTED, $types);
        $this->assertArrayHasKey(Database::CONNECTION_MANAGED, $types);
    }

    public function test_can_scope_managed_databases()
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['user_id' => $user->id]);

        Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'managed_db',
            'engine' => Database::ENGINE_MYSQL,
            'connection_type' => Database::CONNECTION_MANAGED,
            'provider' => Database::PROVIDER_AWS,
        ]);

        Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'self_hosted_db',
            'engine' => Database::ENGINE_MYSQL,
            'connection_type' => Database::CONNECTION_SELF_HOSTED,
        ]);

        $managedDatabases = Database::managed()->get();
        $this->assertCount(1, $managedDatabases);
        $this->assertEquals('managed_db', $managedDatabases->first()->name);
    }

    public function test_can_scope_self_hosted_databases()
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['user_id' => $user->id]);

        Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'managed_db',
            'engine' => Database::ENGINE_MYSQL,
            'connection_type' => Database::CONNECTION_MANAGED,
            'provider' => Database::PROVIDER_AWS,
        ]);

        Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'self_hosted_db',
            'engine' => Database::ENGINE_MYSQL,
            'connection_type' => Database::CONNECTION_SELF_HOSTED,
        ]);

        $selfHostedDatabases = Database::selfHosted()->get();
        $this->assertCount(1, $selfHostedDatabases);
        $this->assertEquals('self_hosted_db', $selfHostedDatabases->first()->name);
    }

    public function test_default_connection_type_is_self_hosted()
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['user_id' => $user->id]);

        $database = Database::create([
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'name' => 'test_db',
            'engine' => Database::ENGINE_MYSQL,
        ]);

        // Default should be self-hosted based on migration
        $this->assertEquals(Database::CONNECTION_SELF_HOSTED, $database->connection_type);
    }
}
