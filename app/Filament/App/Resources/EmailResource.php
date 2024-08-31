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

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

    protected function handleRecordCreation(array $data): Email
    {
        try {
            $email = Email::create($data);

            Queue::push(new GenerateEmailConfigurations($email));
            Queue::push(new UpdateEmailServers());

            Log::info("Email account creation job queued", ['email' => $email->email]);
            return $email;
        } catch (\Exception $e) {
            Log::error("Failed to queue email account creation", ['email' => $data['email'], 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function handleRecordUpdate(Email $email, array $data): Email
    {
        try {
            $email->update($data);

            Queue::push(new UpdateEmailConfigurations($email));
            Queue::push(new UpdateEmailServers());

            Log::info("Email account update job queued", ['email' => $email->email]);
            return $email;
        } catch (\Exception $e) {
            Log::error("Failed to queue email account update", ['email' => $email->email, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function handleRecordDeletion(Email $email)
    {
        try {
            Queue::push(new DeleteEmailConfigurations($email));
            Queue::push(new UpdateEmailServers());

            $email->delete();

            Log::info("Email account deletion job queued", ['email' => $email->email]);
        } catch (\Exception $e) {
            Log::error("Failed to queue email account deletion", ['email' => $email->email, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function updateEmailServers()
    {
        try {
            Queue::push(new UpdateEmailServers());
            Log::info("Email servers update job queued");
        } catch (\Exception $e) {
            Log::error("Failed to queue email servers update", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function generateDovecotConfig(string $email, string $password): string
    {
        try {
            return (new DovecotConfigGenerator)->generate($email, $password);
        } catch (\Exception $e) {
            Log::error("Failed to generate Dovecot configuration", ['email' => $email, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function generatePostfixConfig(string $email, string $password): string
    {
        try {
            return (new PostfixConfigGenerator)->generate($email, $password);
        } catch (\Exception $e) {
            Log::error("Failed to generate Postfix configuration", ['email' => $email, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function restartContainers(): void
    {
        try {
            (new ContainerRestarter)->restart();
            Log::info("Containers restarted successfully");
        } catch (\Exception $e) {
            Log::error("Failed to restart containers", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
