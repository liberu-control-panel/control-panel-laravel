<?php

namespace Tests\Unit\Services;

use App\Services\CloudProviderManager;
use App\Services\DeploymentDetectionService;
use Tests\TestCase;
use Mockery;

class CloudProviderManagerTest extends TestCase
{
    /** @test */
    public function it_can_register_providers()
    {
        $detectionService = Mockery::mock(DeploymentDetectionService::class);
        $manager = new CloudProviderManager($detectionService);

        $this->assertIsArray($manager->getProviders());
    }

    /** @test */
    public function it_checks_auto_scaling_availability()
    {
        $detectionService = Mockery::mock(DeploymentDetectionService::class);
        $detectionService->shouldReceive('supportsAutoScaling')
            ->andReturn(true);

        $manager = new CloudProviderManager($detectionService);
        
        $this->assertTrue($manager->isAutoScalingAvailable());
    }

    /** @test */
    public function it_gets_current_provider_name()
    {
        $detectionService = Mockery::mock(DeploymentDetectionService::class);
        $detectionService->shouldReceive('detectCloudProvider')
            ->andReturn('aws');

        $manager = new CloudProviderManager($detectionService);
        
        $this->assertEquals('aws', $manager->getCurrentProviderName());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
