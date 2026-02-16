<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\GitDeploymentResource\Pages;
use App\Models\GitDeployment;
use App\Services\GitDeploymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class GitDeploymentResource extends Resource
{
    protected static ?string $model = GitDeployment::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $navigationLabel = 'Git Deployments';

    protected static string | \UnitEnum | null $navigationGroup = 'Applications';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Domain Configuration')
                    ->schema([
                        Forms\Components\Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('deploy_path')
                            ->label('Deploy Path')
                            ->default('/public_html')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Path relative to domain root where code will be deployed'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Repository Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('repository_url')
                            ->label('Repository URL')
                            ->required()
                            ->url()
                            ->maxLength(500)
                            ->helperText('Git repository URL (https:// or git@)')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $type = GitDeployment::detectRepositoryType($state);
                                    $set('repository_type', $type);
                                }
                            }),

                        Forms\Components\Select::make('repository_type')
                            ->label('Repository Type')
                            ->options([
                                'github' => 'GitHub',
                                'gitlab' => 'GitLab',
                                'bitbucket' => 'Bitbucket',
                                'other' => 'Other',
                            ])
                            ->default('other')
                            ->required(),

                        Forms\Components\TextInput::make('branch')
                            ->label('Branch')
                            ->default('main')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Branch to deploy (e.g., main, master, develop)'),

                        Forms\Components\Textarea::make('deploy_key')
                            ->label('Deploy Key (Private Key)')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('SSH private key for accessing private repositories (optional)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Build & Deploy Commands')
                    ->schema([
                        Forms\Components\Textarea::make('build_command')
                            ->label('Build Command')
                            ->rows(3)
                            ->placeholder('npm install && npm run build')
                            ->helperText('Commands to run after code is pulled (optional)'),

                        Forms\Components\Textarea::make('deploy_command')
                            ->label('Deploy Command')
                            ->rows(3)
                            ->placeholder('composer install --no-dev')
                            ->helperText('Commands to run for deployment (optional)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Automation')
                    ->schema([
                        Forms\Components\Toggle::make('auto_deploy')
                            ->label('Enable Auto-Deploy')
                            ->helperText('Automatically deploy on webhook triggers')
                            ->default(false),

                        Forms\Components\TextInput::make('webhook_secret')
                            ->label('Webhook Secret')
                            ->maxLength(255)
                            ->helperText('Secret token for validating webhooks')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('generate')
                                    ->icon('heroicon-o-key')
                                    ->action(function (Forms\Set $set) {
                                        $service = app(GitDeploymentService::class);
                                        $set('webhook_secret', $service->generateWebhookSecret());
                                        
                                        Notification::make()
                                            ->title('Webhook secret generated')
                                            ->success()
                                            ->send();
                                    })
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain.domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('repository_name')
                    ->label('Repository')
                    ->searchable(),

                Tables\Columns\TextColumn::make('repository_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'github' => 'success',
                        'gitlab' => 'warning',
                        'bitbucket' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('branch')
                    ->label('Branch')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'deployed' => 'success',
                        'cloning' => 'warning',
                        'updating' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('auto_deploy')
                    ->label('Auto Deploy')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_deployed_at')
                    ->label('Last Deployed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('repository_type')
                    ->options([
                        'github' => 'GitHub',
                        'gitlab' => 'GitLab',
                        'bitbucket' => 'Bitbucket',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'cloning' => 'Cloning',
                        'deployed' => 'Deployed',
                        'failed' => 'Failed',
                        'updating' => 'Updating',
                    ]),

                Tables\Filters\TernaryFilter::make('auto_deploy')
                    ->label('Auto Deploy Enabled'),
            ])
            ->actions([
                Tables\Actions\Action::make('deploy')
                    ->label('Deploy')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (GitDeployment $record) {
                        $service = app(GitDeploymentService::class);
                        
                        if ($service->deploy($record)) {
                            Notification::make()
                                ->title('Deployment started')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Deployment failed')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('viewInfo')
                    ->label('Repository Info')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->action(function (GitDeployment $record) {
                        $service = app(GitDeploymentService::class);
                        $info = $service->getRepositoryInfo($record);
                        
                        if ($info) {
                            Notification::make()
                                ->title('Repository Information')
                                ->body("Branch: {$info['branch']}\nCommit: {$info['commit_hash']}\nAuthor: {$info['commit_author']}\nDate: {$info['commit_date']}\nMessage: {$info['commit_message']}")
                                ->info()
                                ->duration(10000)
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Could not retrieve repository information')
                                ->warning()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('viewLogs')
                    ->label('View Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->modalContent(fn (GitDeployment $record) => view('filament.app.resources.git-deployment-logs', [
                        'log' => $record->deployment_log
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\EditAction::make(),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGitDeployments::route('/'),
            'create' => Pages\CreateGitDeployment::route('/create'),
            'edit' => Pages\EditGitDeployment::route('/{record}/edit'),
        ];
    }
}
