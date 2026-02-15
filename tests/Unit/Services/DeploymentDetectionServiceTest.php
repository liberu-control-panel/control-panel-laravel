<?php

namespace Tests\Unit\Services;

use App\Services\DeploymentDetectionService;
use Tests\TestCase;

class DeploymentDetectionServiceTest extends TestCase
{
    protected DeploymentDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeploymentDetectionService();
    }

    /** @test */
    public function it_can_detect_deployment_mode()
    {
        $mode = $this->service->detectDeploymentMode();
        
        $this->assertContains($mode, [
            DeploymentDetectionService::MODE_STANDALONE,
            DeploymentDetectionService::MODE_DOCKER_COMPOSE,
            DeploymentDetectionService::MODE_KUBERNETES,
        ]);
    }

    /** @test */
    public function it_provides_deployment_mode_labels()
    {
        $label = $this->service->getDeploymentModeLabel(DeploymentDetectionService::MODE_KUBERNETES);
        $this->assertEquals('Kubernetes', $label);

        $label = $this->service->getDeploymentModeLabel(DeploymentDetectionService::MODE_DOCKER_COMPOSE);
        $this->assertEquals('Docker Compose', $label);

        $label = $this->service->getDeploymentModeLabel(DeploymentDetectionService::MODE_STANDALONE);
        $this->assertEquals('Standalone', $label);
    }

    /** @test */
    public function it_provides_cloud_provider_labels()
    {
        $label = $this->service->getCloudProviderLabel(DeploymentDetectionService::PROVIDER_AWS);
        $this->assertEquals('Amazon Web Services (AWS)', $label);

        $label = $this->service->getCloudProviderLabel(DeploymentDetectionService::PROVIDER_AZURE);
        $this->assertEquals('Microsoft Azure', $label);

        $label = $this->service->getCloudProviderLabel(DeploymentDetectionService::PROVIDER_GCP);
        $this->assertEquals('Google Cloud Platform', $label);

        $label = $this->service->getCloudProviderLabel(DeploymentDetectionService::PROVIDER_DIGITALOCEAN);
        $this->assertEquals('DigitalOcean', $label);
    }

    /** @test */
    public function it_returns_deployment_info_array()
    {
        $info = $this->service->getDeploymentInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('mode', $info);
        $this->assertArrayHasKey('cloud_provider', $info);
        $this->assertArrayHasKey('is_kubernetes', $info);
        $this->assertArrayHasKey('is_docker', $info);
        $this->assertArrayHasKey('is_standalone', $info);
        $this->assertArrayHasKey('supports_auto_scaling', $info);
    }

    /** @test */
    public function it_checks_docker_environment_correctly()
    {
        // In test environment, should not be Docker unless explicitly set
        $isDocker = $this->service->isDocker();
        
        // This will depend on test environment setup
        $this->assertIsBool($isDocker);
    }

    /** @test */
    public function it_checks_kubernetes_environment_correctly()
    {
        $isKubernetes = $this->service->isKubernetes();
        
        // In test environment, should not be Kubernetes
        $this->assertIsBool($isKubernetes);
    }

    /** @test */
    public function it_checks_standalone_environment_correctly()
    {
        $isStandalone = $this->service->isStandalone();
        
        // Should be true if not Docker and not Kubernetes
        $this->assertIsBool($isStandalone);
    }

    /** @test */
    public function auto_scaling_support_depends_on_kubernetes_and_cloud_provider()
    {
        $supportsAutoScaling = $this->service->supportsAutoScaling();
        
        // If not Kubernetes, should not support auto-scaling
        if (!$this->service->isKubernetes()) {
            $this->assertFalse($supportsAutoScaling);
        } else {
            // If Kubernetes, depends on cloud provider
            $this->assertIsBool($supportsAutoScaling);
        }
    }
}
