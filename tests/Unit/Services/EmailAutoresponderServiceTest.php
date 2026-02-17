<?php

namespace Tests\Unit\Services;

use App\Models\EmailAccount;
use App\Models\Domain;
use App\Models\User;
use App\Services\EmailAutoresponderService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailAutoresponderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $user;
    protected $domain;
    protected $emailAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new EmailAutoresponderService();
        
        // Create test data
        $this->user = User::factory()->create();
        $this->domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'domain_name' => 'test.com',
        ]);
        $this->emailAccount = EmailAccount::factory()->create([
            'user_id' => $this->user->id,
            'domain_id' => $this->domain->id,
            'email_address' => 'test@test.com',
        ]);
    }

    /** @test */
    public function it_can_setup_autoresponder()
    {
        $data = [
            'enabled' => true,
            'subject' => 'Out of Office',
            'message' => 'I am currently out of office.',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ];

        $result = $this->service->setupAutoresponder($this->emailAccount, $data);

        $this->assertTrue($result->autoresponder_enabled);
        $this->assertEquals('Out of Office', $result->autoresponder_subject);
        $this->assertEquals('I am currently out of office.', $result->autoresponder_message);
    }

    /** @test */
    public function it_can_disable_autoresponder()
    {
        $this->emailAccount->update([
            'autoresponder_enabled' => true,
            'autoresponder_subject' => 'Test',
            'autoresponder_message' => 'Test message',
        ]);

        $result = $this->service->disableAutoresponder($this->emailAccount);

        $this->assertFalse($result->autoresponder_enabled);
    }

    /** @test */
    public function it_checks_if_autoresponder_is_active_based_on_dates()
    {
        // Future start date
        $this->emailAccount->update([
            'autoresponder_enabled' => true,
            'autoresponder_start_date' => now()->addDay(),
        ]);
        $this->assertFalse($this->service->isAutoresponderActive($this->emailAccount));

        // Past end date
        $this->emailAccount->update([
            'autoresponder_enabled' => true,
            'autoresponder_start_date' => now()->subDays(10),
            'autoresponder_end_date' => now()->subDay(),
        ]);
        $this->assertFalse($this->service->isAutoresponderActive($this->emailAccount));

        // Currently active
        $this->emailAccount->update([
            'autoresponder_enabled' => true,
            'autoresponder_start_date' => now()->subDay(),
            'autoresponder_end_date' => now()->addDay(),
        ]);
        $this->assertTrue($this->service->isAutoresponderActive($this->emailAccount));
    }
}
