<?php

namespace App\Filament\App\Resources\Domains\Actions;

use App\Models\Domain;
use App\Services\CloudProviderManager;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

class ManageScalingAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'manage_scaling';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Manage Scaling')
            ->icon('heroicon-o-arrows-pointing-out')
            ->color('primary')
            ->visible(fn (Domain $record) => $this->canScale($record))
            ->form(fn (Domain $record) => $this->getScalingForm($record))
            ->action(function (Domain $record, array $data) {
                $this->handleScaling($record, $data);
            });
    }

    protected function canScale(Domain $record): bool
    {
        $server = $record->server;
        if (!$server) {
            return false;
        }

        return $server->supportsAutoScaling();
    }

    protected function getScalingForm(Domain $record): array
    {
        $cloudProviderManager = app(CloudProviderManager::class);
        $provider = $cloudProviderManager->getProvider($record->server);

        if (!$provider) {
            return [];
        }

        $scalingConfig = $provider->getScalingConfig($record);
        $currentReplicas = $provider->getCurrentReplicas($record);

        return [
            Forms\Components\Section::make('Current Status')
                ->schema([
                    Forms\Components\Placeholder::make('current_replicas')
                        ->label('Current Replicas')
                        ->content($currentReplicas),
                    
                    Forms\Components\Placeholder::make('hpa_status')
                        ->label('Horizontal Scaling Status')
                        ->content(fn () => $scalingConfig['horizontal'] 
                            ? 'Enabled (Min: ' . $scalingConfig['horizontal']['min_replicas'] . 
                              ', Max: ' . $scalingConfig['horizontal']['max_replicas'] . ')'
                            : 'Disabled'),
                    
                    Forms\Components\Placeholder::make('vpa_status')
                        ->label('Vertical Scaling Status')
                        ->content(fn () => $scalingConfig['vertical'] 
                            ? 'Enabled (' . $scalingConfig['vertical']['update_mode'] . ')'
                            : 'Disabled'),
                ]),

            Forms\Components\Section::make('Horizontal Scaling (HPA)')
                ->description('Automatically scale pods based on CPU/memory usage')
                ->collapsible()
                ->schema([
                    Forms\Components\Toggle::make('enable_horizontal')
                        ->label('Enable Horizontal Auto-Scaling')
                        ->default(!empty($scalingConfig['horizontal']))
                        ->reactive(),
                    
                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\TextInput::make('min_replicas')
                                ->label('Minimum Replicas')
                                ->numeric()
                                ->default($scalingConfig['horizontal']['min_replicas'] ?? 1)
                                ->minValue(1)
                                ->required(fn (Forms\Get $get) => $get('enable_horizontal')),
                            
                            Forms\Components\TextInput::make('max_replicas')
                                ->label('Maximum Replicas')
                                ->numeric()
                                ->default($scalingConfig['horizontal']['max_replicas'] ?? 10)
                                ->minValue(1)
                                ->required(fn (Forms\Get $get) => $get('enable_horizontal')),
                            
                            Forms\Components\TextInput::make('target_cpu')
                                ->label('Target CPU %')
                                ->numeric()
                                ->default(80)
                                ->suffix('%')
                                ->minValue(1)
                                ->maxValue(100)
                                ->required(fn (Forms\Get $get) => $get('enable_horizontal')),
                        ])
                        ->visible(fn (Forms\Get $get) => $get('enable_horizontal')),
                ]),

            Forms\Components\Section::make('Vertical Scaling (VPA)')
                ->description('Automatically adjust CPU and memory limits')
                ->collapsible()
                ->visible(fn () => $provider->supportsVerticalScaling())
                ->schema([
                    Forms\Components\Toggle::make('enable_vertical')
                        ->label('Enable Vertical Auto-Scaling')
                        ->default(!empty($scalingConfig['vertical']))
                        ->reactive(),
                    
                    Forms\Components\Select::make('vpa_update_mode')
                        ->label('Update Mode')
                        ->options([
                            'Off' => 'Off - Only provide recommendations',
                            'Initial' => 'Initial - Apply on pod creation',
                            'Recreate' => 'Recreate - Delete and recreate pods',
                            'Auto' => 'Auto - Update pods automatically',
                        ])
                        ->default($scalingConfig['vertical']['update_mode'] ?? 'Auto')
                        ->required(fn (Forms\Get $get) => $get('enable_vertical'))
                        ->visible(fn (Forms\Get $get) => $get('enable_vertical')),
                ]),

            Forms\Components\Section::make('Manual Scaling')
                ->description('Manually set the number of replicas')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('manual_replicas')
                        ->label('Number of Replicas')
                        ->numeric()
                        ->default($currentReplicas)
                        ->minValue(0)
                        ->helperText('Set to 0 to stop the application'),
                ]),
        ];
    }

    protected function handleScaling(Domain $record, array $data): void
    {
        $cloudProviderManager = app(CloudProviderManager::class);
        $provider = $cloudProviderManager->getProvider($record->server);

        if (!$provider) {
            Notification::make()
                ->title('Scaling not available')
                ->body('Cloud provider not detected or not supported')
                ->danger()
                ->send();
            return;
        }

        try {
            // Handle horizontal scaling
            if (isset($data['enable_horizontal'])) {
                if ($data['enable_horizontal']) {
                    $provider->enableHorizontalScaling(
                        $record,
                        $data['min_replicas'] ?? 1,
                        $data['max_replicas'] ?? 10,
                        $data['target_cpu'] ?? 80
                    );
                } else {
                    $provider->disableHorizontalScaling($record);
                }
            }

            // Handle vertical scaling
            if (isset($data['enable_vertical']) && $provider->supportsVerticalScaling()) {
                if ($data['enable_vertical']) {
                    $provider->enableVerticalScaling($record, [
                        'update_mode' => $data['vpa_update_mode'] ?? 'Auto',
                    ]);
                } else {
                    $provider->disableVerticalScaling($record);
                }
            }

            // Handle manual scaling
            if (isset($data['manual_replicas'])) {
                $provider->scaleToReplicas($record, (int) $data['manual_replicas']);
            }

            Notification::make()
                ->title('Scaling configuration updated')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to update scaling configuration')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
