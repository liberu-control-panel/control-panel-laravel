<?php

namespace App\Filament\App\Resources\Domains;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\Domains\Pages\ListDomains;
use App\Filament\App\Resources\Domains\Pages\CreateDomain;
use App\Filament\App\Resources\Domains\Pages\EditDomain;
use App\Filament\App\Resources\DomainResource\Pages;
use App\Filament\App\Resources\DomainResource\RelationManagers;
use App\Models\Domain;
use App\Models\Server;
use App\Models\UserHostingPlan;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Domains';

    protected static ?string $modelLabel = 'Domain';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Domain Information')
                    ->description('Configure the basic domain settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('domain_name')
                                    ->label('Domain Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Domain::class, 'domain_name', ignoreRecord: true)
                                    ->placeholder('example.com')
                                    ->helperText('Enter a valid domain name (e.g., example.com)')
                                    ->rules([
                                        'regex:/^(?!:\/\/)([a-zA-Z0-9-_]{1,63}\.)*[a-zA-Z0-9][a-zA-Z0-9-]{0,62}\.[a-zA-Z]{2,}$/'
                                    ])
                                    ->validationMessages([
                                        'regex' => 'Please enter a valid domain name (e.g., example.com)',
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $set('virtual_host', $state);
                                            $set('letsencrypt_host', $state);
                                        }
                                    }),
                                
                                Select::make('server_id')
                                    ->label('Server')
                                    ->relationship('server', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Select the server where this domain will be hosted')
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('ip_address')->required()->ip(),
                                    ]),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('registration_date')
                                    ->label('Registration Date')
                                    ->required()
                                    ->default(now())
                                    ->displayFormat('M d, Y')
                                    ->helperText('Date when the domain was registered'),
                                
                                DatePicker::make('expiration_date')
                                    ->label('Expiration Date')
                                    ->required()
                                    ->minDate(now())
                                    ->displayFormat('M d, Y')
                                    ->helperText('Domain expiration date - you\'ll be notified before it expires')
                                    ->afterOrEqual('registration_date'),
                            ]),
                    ]),

                Section::make('SSL Configuration')
                    ->description('Configure SSL/TLS certificates via Let\'s Encrypt')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('letsencrypt_host')
                                    ->label('Let\'s Encrypt Host')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('example.com')
                                    ->helperText('Domain for Let\'s Encrypt certificate (auto-filled from domain name)'),
                                
                                TextInput::make('letsencrypt_email')
                                    ->label('Let\'s Encrypt Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('admin@example.com')
                                    ->helperText('Email address for SSL certificate notifications'),
                            ]),
                    ]),

                Section::make('Server Access Credentials')
                    ->description('Configure SFTP and SSH access for file management')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('sftp_username')
                                    ->label('SFTP Username')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('ftpuser')
                                    ->helperText('Username for SFTP file access')
                                    ->alphaDash(),
                                
                                TextInput::make('sftp_password')
                                    ->label('SFTP Password')
                                    ->password()
                                    ->required()
                                    ->maxLength(255)
                                    ->revealable()
                                    ->helperText('Minimum 8 characters recommended')
                                    ->minLength(8),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('ssh_username')
                                    ->label('SSH Username')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('sshuser')
                                    ->helperText('Username for SSH access')
                                    ->alphaDash(),
                                
                                TextInput::make('ssh_password')
                                    ->label('SSH Password')
                                    ->password()
                                    ->required()
                                    ->maxLength(255)
                                    ->revealable()
                                    ->helperText('Minimum 8 characters recommended')
                                    ->minLength(8),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain_name')
                    ->label('Domain Name')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Domain copied to clipboard')
                    ->icon('heroicon-m-globe-alt')
                    ->weight('medium'),
                
                TextColumn::make('server.name')
                    ->label('Server')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->default('N/A'),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(function (Domain $record) {
                        if ($record->expiration_date && $record->expiration_date->isPast()) {
                            return 'expired';
                        }
                        if ($record->expiration_date && $record->expiration_date->diffInDays(now()) <= 30) {
                            return 'expiring_soon';
                        }
                        return 'active';
                    })
                    ->colors([
                        'success' => 'active',
                        'warning' => 'expiring_soon',
                        'danger' => 'expired',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'active',
                        'heroicon-o-exclamation-triangle' => 'expiring_soon',
                        'heroicon-o-x-circle' => 'expired',
                    ]),
                
                TextColumn::make('registration_date')
                    ->label('Registered')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('expiration_date')
                    ->label('Expires')
                    ->date('M d, Y')
                    ->sortable()
                    ->description(function (Domain $record) {
                        if ($record->expiration_date) {
                            $daysLeft = $record->expiration_date->diffInDays(now());
                            return $daysLeft > 0 ? "{$daysLeft} days left" : 'Expired';
                        }
                        return '';
                    })
                    ->color(function (Domain $record) {
                        if ($record->expiration_date && $record->expiration_date->isPast()) {
                            return 'danger';
                        }
                        if ($record->expiration_date && $record->expiration_date->diffInDays(now()) <= 30) {
                            return 'warning';
                        }
                        return 'success';
                    }),
                
                TextColumn::make('dnsSettings_count')
                    ->label('DNS Records')
                    ->counts('dnsSettings')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('server')
                    ->relationship('server', 'name')
                    ->multiple()
                    ->preload(),
                
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring in 30 Days')
                    ->query(fn (Builder $query): Builder => $query->where('expiration_date', '<=', now()->addDays(30)))
                    ->toggle(),
                
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query->where('expiration_date', '<', now()))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
                Tables\Actions\Action::make('manage_dns')
                    ->label('Manage DNS')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (Domain $record) => route('filament.app.resources.dns-settings.index', [
                        'tableFilters' => ['domain_id' => ['value' => $record->id]]
                    ]))
                    ->openUrlInNewTab(false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public function __construct(protected DomainContainerRestarter $containerRestarter)
    {
        // ...
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDomains::route('/'),
            'create' => CreateDomain::route('/create'),
            'edit' => EditDomain::route('/{record}/edit'),
        ];
    }


}
