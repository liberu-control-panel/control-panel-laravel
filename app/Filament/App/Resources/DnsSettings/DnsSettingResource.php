<?php

namespace App\Filament\App\Resources\DnsSettings;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\DnsSettings\Pages\ListDnsSettings;
use App\Filament\App\Resources\DnsSettings\Pages\CreateDnsSetting;
use App\Filament\App\Resources\DnsSettings\Pages\EditDnsSetting;
use App\Filament\App\Resources\DnsSettingResource\Pages;
use App\Filament\App\Resources\DnsSettingResource\RelationManagers;
use App\Models\DnsSetting;
use App\Models\Domain;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Validation\Rule;

use App\Services\DnsSettingService;

class DnsSettingResource extends Resource {
    protected static ?string $model = DnsSetting::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'DNS Records';

    protected static ?string $modelLabel = 'DNS Record';

    public function __construct(protected DnsSettingService $dnsSettingService)
    {
        // ...
    }

    public static function form(Schema $schema): Schema {
        return $schema
            ->components([
                Section::make('DNS Record Details')
                    ->description('Configure DNS record for your domain')
                    ->schema([
                        Select::make('domain_id')
                            ->label('Domain')
                            ->relationship('domain', 'domain_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the domain for this DNS record')
                            ->createOptionForm([
                                TextInput::make('domain_name')->required(),
                            ]),
                        
                        Select::make('record_type')
                            ->label('Record Type')
                            ->required()
                            ->options([
                                'A' => 'A - IPv4 Address',
                                'AAAA' => 'AAAA - IPv6 Address',
                                'CNAME' => 'CNAME - Canonical Name',
                                'MX' => 'MX - Mail Exchange',
                                'TXT' => 'TXT - Text Record',
                                'NS' => 'NS - Name Server',
                                'PTR' => 'PTR - Pointer Record',
                                'SRV' => 'SRV - Service Record',
                            ])
                            ->reactive()
                            ->helperText('Choose the type of DNS record')
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Set default TTL based on record type
                                if ($state === 'MX') {
                                    $set('ttl', 3600);
                                    $set('priority', 10);
                                } else {
                                    $set('ttl', 3600);
                                }
                            }),
                    ]),

                Section::make('Record Configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Record Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('@')
                                    ->helperText('Use @ for root domain, or enter subdomain (e.g., www, mail)')
                                    ->rules([
                                        'regex:/^(@|[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)$/'
                                    ])
                                    ->validationMessages([
                                        'regex' => 'Invalid record name. Use @ for root or a valid subdomain.',
                                    ]),
                                
                                TextInput::make('value')
                                    ->label('Record Value')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(function ($get) {
                                        return match($get('record_type')) {
                                            'A' => '192.0.2.1',
                                            'AAAA' => '2001:0db8::1',
                                            'CNAME' => 'example.com',
                                            'MX' => 'mail.example.com',
                                            'TXT' => 'v=spf1 include:_spf.example.com ~all',
                                            'NS' => 'ns1.example.com',
                                            default => 'Enter value...',
                                        };
                                    })
                                    ->helperText(function ($get) {
                                        return match($get('record_type')) {
                                            'A' => 'Enter IPv4 address (e.g., 192.0.2.1)',
                                            'AAAA' => 'Enter IPv6 address (e.g., 2001:0db8::1)',
                                            'CNAME' => 'Enter target domain name',
                                            'MX' => 'Enter mail server hostname',
                                            'TXT' => 'Enter text value (SPF, DKIM, verification, etc.)',
                                            'NS' => 'Enter nameserver hostname',
                                            default => 'Enter the record value',
                                        };
                                    })
                                    ->rules(function ($get) {
                                        $rules = ['required'];
                                        
                                        $recordType = $get('record_type');
                                        if ($recordType === 'A') {
                                            $rules[] = 'ipv4';
                                        } elseif ($recordType === 'AAAA') {
                                            $rules[] = 'ipv6';
                                        } elseif (in_array($recordType, ['CNAME', 'MX', 'NS'])) {
                                            $rules[] = 'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]*(\.[a-zA-Z0-9][a-zA-Z0-9-]*)*$/';
                                        }
                                        
                                        return $rules;
                                    })
                                    ->validationMessages([
                                        'ipv4' => 'Please enter a valid IPv4 address',
                                        'ipv6' => 'Please enter a valid IPv6 address',
                                        'regex' => 'Please enter a valid domain name',
                                    ]),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('ttl')
                                    ->label('TTL (Time To Live)')
                                    ->required()
                                    ->numeric()
                                    ->default(3600)
                                    ->minValue(60)
                                    ->maxValue(86400)
                                    ->suffix('seconds')
                                    ->helperText('Recommended: 3600 (1 hour). Lower values propagate changes faster.')
                                    ->rules(['integer', 'min:60', 'max:86400']),
                                
                                TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(10)
                                    ->minValue(0)
                                    ->maxValue(65535)
                                    ->visible(fn ($get) => $get('record_type') === 'MX')
                                    ->required(fn ($get) => $get('record_type') === 'MX')
                                    ->helperText('Lower values have higher priority (e.g., 10 is higher than 20)')
                                    ->rules(['integer', 'min:0', 'max:65535']),
                            ]),
                    ]),

                Section::make('Information')
                    ->schema([
                        Placeholder::make('dns_info')
                            ->label('')
                            ->content(function ($get) {
                                $recordType = $get('record_type');
                                $tips = [
                                    'A' => '✓ Points your domain to an IPv4 address\n✓ Most common record type\n✓ Required for website hosting',
                                    'AAAA' => '✓ Points your domain to an IPv6 address\n✓ Used for modern IPv6 connectivity\n✓ Optional but recommended',
                                    'CNAME' => '✓ Creates an alias to another domain\n✓ Cannot be used on root domain (@)\n✓ Useful for subdomains',
                                    'MX' => '✓ Directs email to mail servers\n✓ Requires priority value\n✓ Multiple records allowed for redundancy',
                                    'TXT' => '✓ Stores text information\n✓ Used for SPF, DKIM, domain verification\n✓ Maximum 255 characters per string',
                                    'NS' => '✓ Delegates subdomain to other nameservers\n✓ Requires multiple records for redundancy\n✓ Advanced usage',
                                ];
                                
                                return $tips[$recordType] ?? '✓ Configure your DNS record above';
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('domain.domain_name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-globe-alt')
                    ->weight('medium'),
                
                BadgeColumn::make('record_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'A',
                        'success' => 'AAAA',
                        'warning' => 'CNAME',
                        'danger' => 'MX',
                        'info' => 'TXT',
                        'secondary' => ['NS', 'PTR', 'SRV'],
                    ])
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('name')
                    ->label('Record Name')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Record name copied')
                    ->formatStateUsing(fn ($state) => $state === '@' ? '@ (root)' : $state),
                
                TextColumn::make('value')
                    ->label('Value')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Value copied to clipboard')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                
                TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->toggleable(),
                
                TextColumn::make('ttl')
                    ->label('TTL')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? "{$state}s" : 'N/A')
                    ->description(function ($state) {
                        if (!$state) return '';
                        $hours = floor($state / 3600);
                        $minutes = floor(($state % 3600) / 60);
                        if ($hours > 0) {
                            return "{$hours}h" . ($minutes > 0 ? " {$minutes}m" : '');
                        }
                        return $minutes > 0 ? "{$minutes}m" : "{$state}s";
                    }),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('domain_id')
                    ->label('Domain')
                    ->relationship('domain', 'domain_name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('record_type')
                    ->label('Record Type')
                    ->options([
                        'A' => 'A - IPv4',
                        'AAAA' => 'AAAA - IPv6',
                        'CNAME' => 'CNAME',
                        'MX' => 'MX - Mail',
                        'TXT' => 'TXT',
                        'NS' => 'NS',
                        'PTR' => 'PTR',
                        'SRV' => 'SRV',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    
                    Tables\Actions\BulkAction::make('update_ttl')
                        ->label('Update TTL')
                        ->icon('heroicon-o-clock')
                        ->form([
                            TextInput::make('ttl')
                                ->label('New TTL Value')
                                ->numeric()
                                ->required()
                                ->default(3600)
                                ->minValue(60)
                                ->suffix('seconds'),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                $record->update(['ttl' => $data['ttl']]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getRelations(): array {
        return [
            //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => ListDnsSettings::route('/'),
            'create' => CreateDnsSetting::route('/create'),
            'edit' => EditDnsSetting::route('/{record}/edit'),
        ];
    }
}
