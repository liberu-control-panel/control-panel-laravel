<?php

namespace App\Filament\App\Resources;

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

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $navigationIcon = 'heroicon-o-mail';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEmails::route('/'),
        ];
    }

    protected function handleRecordCreation(array $data): Email
    {
        $email = Email::create($data);

        // Generate Dovecot configuration
        $dovecotConfig = $this->generateDovecotConfig($email->email, $email->password);
        Storage::disk('dovecot_config')->put($email->email . '.conf', $dovecotConfig);

        // Generate Postfix configuration
        $postfixConfig = $this->generatePostfixConfig($email->email, $email->password);
        Storage::disk('postfix_config')->put($email->email . '.cf', $postfixConfig);

        // Create mailbox directory
        Storage::disk('dovecot_data')->makeDirectory($email->email);

        // Update Dovecot and Postfix Docker instances
        $this->updateEmailServers();

        return $email;
    }

    protected function handleRecordUpdate(Email $email, array $data): Email
    {
        $email->update($data);

        // Update Dovecot configuration
        $dovecotConfig = $this->generateDovecotConfig($email->email, $email->password);
        Storage::disk('dovecot_config')->put($email->email . '.conf', $dovecotConfig);

        // Update Postfix configuration
        $postfixConfig = $this->generatePostfixConfig($email->email, $email->password);
        Storage::disk('postfix_config')->put($email->email . '.cf', $postfixConfig);

        // Update Dovecot and Postfix Docker instances
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

        // Update Dovecot and Postfix Docker instances
        $this->updateEmailServers();

        $email->delete();
    }

    protected function updateEmailServers()
    {
        $this->callSilent('email-servers:update');
    }

    protected function generateDovecotConfig(string $email, string $password): string
    {
        return (new DovecotConfigGenerator)->generate($email, $password);
    }
    
    protected function generatePostfixConfig(string $email, string $password): string
    {
        return (new PostfixConfigGenerator)->generate($email, $password);
    }

    protected function restartContainers(): void
    {
        (new ContainerRestarter)->restart();
    }
}
