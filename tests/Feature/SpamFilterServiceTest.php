<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\User;
use App\Services\DeploymentDetectionService;
use App\Services\SpamFilterService;
use App\Services\StandaloneServiceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SpamFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Domain $domain;
    protected EmailAccount $emailAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user   = User::factory()->create();
        $this->domain = Domain::factory()->create(['user_id' => $this->user->id]);
        $this->emailAccount = EmailAccount::factory()->create([
            'user_id'            => $this->user->id,
            'domain_id'          => $this->domain->id,
            'spam_filter_enabled' => false,
            'spam_threshold'     => 5,
            'spam_action'        => 'tag',
        ]);
    }

    public function test_set_spam_filter_enabled_updates_email_account(): void
    {
        $detection = Mockery::mock(DeploymentDetectionService::class);
        $detection->shouldReceive('isStandalone')->andReturn(true);

        $helper = Mockery::mock(StandaloneServiceHelper::class);

        $service = new SpamFilterService($detection, $helper);

        // Enable spam filter (file write will fail silently in test env)
        $result = $service->setSpamFilterEnabled($this->emailAccount, true);

        // The model should be updated
        $this->assertTrue($this->emailAccount->fresh()->spam_filter_enabled);
    }

    public function test_update_spam_settings_persists_to_database(): void
    {
        $this->emailAccount->update(['spam_filter_enabled' => false]);

        $detection = Mockery::mock(DeploymentDetectionService::class);
        $detection->shouldReceive('isStandalone')->andReturn(true);

        $helper = Mockery::mock(StandaloneServiceHelper::class);

        $service = new SpamFilterService($detection, $helper);

        $service->updateSpamSettings($this->emailAccount, 8, 'move_to_spam');

        $fresh = $this->emailAccount->fresh();
        $this->assertEquals(8, $fresh->spam_threshold);
        $this->assertEquals('move_to_spam', $fresh->spam_action);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
