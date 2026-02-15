<?php

namespace App\Filament\App\Resources\Emails;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\Emails\Pages\ManageEmails;
use App\Filament\App\Resources\EmailResource\Pages;
use App\Models\Email;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use App\Filament\App\Resources\EmailResource\DovecotConfigGenerator;
use App\Filament\App\Resources\EmailResource\PostfixConfigGenerator;
use App\Filament\App\Resources\EmailResource\ContainerRestarter;
use Filament\Forms\Components\Repeater;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-mail';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->maxLength(255),
                Repeater::make('forwarding_rules')
                    ->schema([
                        TextInput::make('destination')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1)
                    ->createItemButtonLabel('Add Forwarding Rule')
                    ->label('Forwarding Rules'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime(),
                TagsColumn::make('forwarding_rules')
                    ->getStateUsing(fn ($record) => $record->forwarding_rules ? array_column($record->forwarding_rules, 'destination') : [])
                    ->label('Forwarding To'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmails::route('/'),
        ];
    }

    protected function handleRecordCreation(array $data): Email
    {
        $email = Email::create($data);

        // Generate Dovecot configuration
        $dovecotConfig = $this->generateDovecotConfig($email->email, $email->password, $email->forwarding_rules);
        Storage::disk('dovecot_config')->put($email->email . '.conf', $dovecotConfig);

        // Generate Postfix configuration
        $postfixConfig = $this->generatePostfixConfig($email->email, $email->password, $email->forwarding_rules);
        Storage::disk('postfix_config')->put($email->email . '.cf', $postfixConfig);

        // Create mailbox directory
        Storage::disk('dovecot_data')->makeDirectory($email->email);

        // Update Dovecot and Postfix services
        $this->updateEmailServers();

        return $email;
    }

    protected function handleRecordUpdate(Email $email, array $data): Email
    {
        $email->update($data);

        // Update Dovecot configuration
        $dovecotConfig = $this->generateDovecotConfig($email->email, $email->password, $email->forwarding_rules);
        Storage::disk('dovecot_config')->put($email->email . '.conf', $dovecotConfig);

        // Update Postfix configuration
        $postfixConfig = $this->generatePostfixConfig($email->email, $email->password, $email->forwarding_rules);
        Storage::disk('postfix_config')->put($email->email . '.cf', $postfixConfig);

        // Update Dovecot and Postfix services
        $this->updateEmailServers();

        return $email;
    }

    protected function handleRecordDeletion(Email $email)
    {
        // Remove Dovecot configuration
        Storage::disk('dovecot_config')->delete($email->email . '.conf');

        // Remove Postfix configuration
        Storage::disk('postfix_config')->delete($email->email . '.cf');

        // Remove mailbox directory
        Storage::disk('dovecot_data')->deleteDirectory($email->email);

        // Update Dovecot and Postfix services
        $this->updateEmailServers();

        $email->delete();
    }

    protected function updateEmailServers()
    {
        $this->callSilent('email-servers:update');
    }

    protected function generateDovecotConfig(string $email, string $password, array $forwardingRules): string
    {
        return (new DovecotConfigGenerator)->generate($email, $password, $forwardingRules);
    }

    protected function generatePostfixConfig(string $email, string $password, array $forwardingRules): string
    {
        return (new PostfixConfigGenerator)->generate($email, $password, $forwardingRules);
    }

    protected function restartContainers(): void
    {
        (new ContainerRestarter)->restart();
    }
}
