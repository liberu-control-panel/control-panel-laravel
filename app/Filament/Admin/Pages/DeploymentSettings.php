<?php

namespace App\Filament\Admin\Pages;

use App\Services\DeploymentDetectionService;
use App\Services\CloudProviderManager;
use App\Models\InstallationMetadata;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class DeploymentSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';
    
    protected static ?string $navigationLabel = 'Deployment';
    
    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.admin.pages.deployment-settings';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $detectionService = app(DeploymentDetectionService::class);
        $deploymentInfo = $detectionService->getDeploymentInfo();

        $this->form->fill([
            'deployment_mode' => $deploymentInfo['mode'],
            'cloud_provider' => $deploymentInfo['cloud_provider'],
            'is_kubernetes' => $deploymentInfo['is_kubernetes'],
            'is_docker' => $deploymentInfo['is_docker'],
            'is_standalone' => $deploymentInfo['is_standalone'],
            'supports_auto_scaling' => $deploymentInfo['supports_auto_scaling'],
            'auto_scaling_enabled' => InstallationMetadata::getValue('auto_scaling_enabled', false),
        ]);
    }

    public function form(Form $form): Form
    {
        $detectionService = app(DeploymentDetectionService::class);
        
        return $form
            ->schema([
                Forms\Components\Section::make('Deployment Information')
                    ->description('Current deployment environment and configuration')
                    ->schema([
                        Forms\Components\Placeholder::make('deployment_mode')
                            ->label('Deployment Mode')
                            ->content(fn ($state) => $detectionService->getDeploymentModeLabel($state)),
                        
                        Forms\Components\Placeholder::make('cloud_provider')
                            ->label('Cloud Provider')
                            ->content(fn ($state) => $detectionService->getCloudProviderLabel($state)),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('is_kubernetes')
                                    ->label('Kubernetes')
                                    ->content(fn ($state) => $state ? '✓ Active' : '✗ Not Active'),
                                
                                Forms\Components\Placeholder::make('is_docker')
                                    ->label('Docker')
                                    ->content(fn ($state) => $state ? '✓ Active' : '✗ Not Active'),
                                
                                Forms\Components\Placeholder::make('is_standalone')
                                    ->label('Standalone')
                                    ->content(fn ($state) => $state ? '✓ Active' : '✗ Not Active'),
                            ]),
                    ]),

                Forms\Components\Section::make('Auto-Scaling Configuration')
                    ->description('Configure automatic horizontal and vertical scaling')
                    ->schema([
                        Forms\Components\Placeholder::make('supports_auto_scaling')
                            ->label('Auto-Scaling Support')
                            ->content(fn ($state) => $state 
                                ? '✓ Supported in this environment' 
                                : '✗ Not supported (requires Kubernetes with cloud provider)'),
                        
                        Forms\Components\Toggle::make('auto_scaling_enabled')
                            ->label('Enable Auto-Scaling Globally')
                            ->helperText('Enable automatic scaling for all domains that support it')
                            ->disabled(fn () => !$this->data['supports_auto_scaling'] ?? true),
                    ]),

                Forms\Components\Section::make('System Capabilities')
                    ->description('Available features based on current deployment')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('horizontal_scaling')
                                    ->label('Horizontal Pod Autoscaling (HPA)')
                                    ->content(fn () => $this->data['supports_auto_scaling'] ?? false 
                                        ? '✓ Available' 
                                        : '✗ Not Available'),
                                
                                Forms\Components\Placeholder::make('vertical_scaling')
                                    ->label('Vertical Pod Autoscaling (VPA)')
                                    ->content(fn () => $this->getVpaStatus()),
                                
                                Forms\Components\Placeholder::make('load_balancing')
                                    ->label('Load Balancing')
                                    ->content(fn () => $this->data['is_kubernetes'] ?? false 
                                        ? '✓ Kubernetes Service Load Balancer' 
                                        : ($this->data['is_docker'] ?? false 
                                            ? '✓ Docker Swarm/Compose' 
                                            : '✗ Not Available')),
                                
                                Forms\Components\Placeholder::make('ssl_automation')
                                    ->label('SSL Certificate Automation')
                                    ->content(fn () => $this->data['is_kubernetes'] ?? false 
                                        ? '✓ cert-manager (Let\'s Encrypt)' 
                                        : '✓ Certbot'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Only save editable fields
        if (isset($data['auto_scaling_enabled'])) {
            InstallationMetadata::setValue('auto_scaling_enabled', $data['auto_scaling_enabled']);
        }

        // Update cached deployment info
        Cache::forget('deployment_info');
        
        // Update installation metadata
        $detectionService = app(DeploymentDetectionService::class);
        $deploymentInfo = $detectionService->getDeploymentInfo();
        
        InstallationMetadata::updateOrCreateMetadata('deployment_mode', $deploymentInfo['mode'], [
            'type' => 'string',
            'description' => 'Current deployment mode',
            'is_editable' => false,
        ]);
        
        InstallationMetadata::updateOrCreateMetadata('cloud_provider', $deploymentInfo['cloud_provider'], [
            'type' => 'string',
            'description' => 'Detected cloud provider',
            'is_editable' => false,
        ]);

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    protected function getVpaStatus(): string
    {
        if (!($this->data['supports_auto_scaling'] ?? false)) {
            return '✗ Not Available';
        }

        $cloudProvider = $this->data['cloud_provider'] ?? '';
        
        $supportedProviders = ['azure', 'aws', 'gcp', 'digitalocean', 'ovh'];
        
        if (in_array($cloudProvider, $supportedProviders)) {
            return '✓ Available (may require addon installation)';
        }

        return '? Unknown (check cloud provider documentation)';
    }
}
