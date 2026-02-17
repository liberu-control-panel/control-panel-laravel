<?php

namespace Tests\Unit\Services;

use App\Models\EmailAuthentication;
use App\Models\Domain;
use App\Models\User;
use App\Services\EmailAuthenticationService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailAuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $user;
    protected $domain;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new EmailAuthenticationService();
        
        $this->user = User::factory()->create();
        $this->domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'domain_name' => 'example.com',
        ]);
    }

    /** @test */
    public function it_can_generate_dkim_keys()
    {
        $keys = $this->service->generateDkimKeys($this->domain);

        $this->assertIsArray($keys);
        $this->assertArrayHasKey('private_key', $keys);
        $this->assertArrayHasKey('public_key', $keys);
        $this->assertArrayHasKey('dns_record', $keys);
        
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $keys['private_key']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $keys['public_key']);
        $this->assertStringContainsString('v=DKIM1', $keys['dns_record']);
    }

    /** @test */
    public function it_can_setup_email_authentication()
    {
        $options = [
            'spf_enabled' => true,
            'dkim_enabled' => true,
            'dmarc_enabled' => true,
            'dmarc_policy' => 'quarantine',
        ];

        $auth = $this->service->setupEmailAuthentication($this->domain, $options);

        $this->assertInstanceOf(EmailAuthentication::class, $auth);
        $this->assertTrue($auth->spf_enabled);
        $this->assertTrue($auth->dkim_enabled);
        $this->assertTrue($auth->dmarc_enabled);
        $this->assertEquals('quarantine', $auth->dmarc_policy);
        $this->assertNotNull($auth->dkim_private_key);
        $this->assertNotNull($auth->dkim_public_key);
    }

    /** @test */
    public function it_generates_spf_record_correctly()
    {
        $auth = $this->service->setupEmailAuthentication($this->domain);

        $this->assertStringContainsString('v=spf1', $auth->spf_record);
        $this->assertStringContainsString('mx', $auth->spf_record);
        $this->assertStringContainsString('~all', $auth->spf_record);
    }

    /** @test */
    public function it_generates_dmarc_record_correctly()
    {
        $options = [
            'dmarc_policy' => 'reject',
            'dmarc_percentage' => 100,
            'dmarc_rua_email' => 'dmarc@example.com',
        ];

        $auth = $this->service->setupEmailAuthentication($this->domain, $options);

        $this->assertStringContainsString('v=DMARC1', $auth->dmarc_record);
        $this->assertStringContainsString('p=reject', $auth->dmarc_record);
        $this->assertStringContainsString('pct=100', $auth->dmarc_record);
        $this->assertStringContainsString('dmarc@example.com', $auth->dmarc_record);
    }

    /** @test */
    public function it_can_get_dns_records()
    {
        $auth = $this->service->setupEmailAuthentication($this->domain);
        $records = $this->service->getDnsRecords($auth);

        $this->assertIsArray($records);
        $this->assertCount(3, $records); // SPF, DKIM, DMARC

        // Check SPF record
        $spfRecord = collect($records)->firstWhere('name', '@');
        $this->assertNotNull($spfRecord);
        $this->assertEquals('TXT', $spfRecord['type']);

        // Check DKIM record
        $dkimRecord = collect($records)->firstWhere('name', 'default._domainkey');
        $this->assertNotNull($dkimRecord);
        $this->assertEquals('TXT', $dkimRecord['type']);

        // Check DMARC record
        $dmarcRecord = collect($records)->firstWhere('name', '_dmarc');
        $this->assertNotNull($dmarcRecord);
        $this->assertEquals('TXT', $dmarcRecord['type']);
    }
}
