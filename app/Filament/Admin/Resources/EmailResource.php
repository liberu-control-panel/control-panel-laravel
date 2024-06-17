<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmailResource\Pages;
use App\Models\Email;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $navigationIcon = 'heroicon-o-mail';

    public static function form(Form $form): Form
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
        Storage::disk('dovecot')->put($email->email, $dovecotConfig);
    
        // Generate Postfix configuration
        $postfixConfig = $this->generatePostfixConfig($email->email, $email->password);
        Storage::disk('postfix')->put($email->email, $postfixConfig);
    
        // Update Dovecot and Postfix Docker instances
        $this->callSilent('email-servers:update');
    
        return $email;
    }

    protected function handleRecordUpdate(Email $email, array $data): Email
    {
        $email->update($data);

        // Update Dovecot configuration
        $dovecotConfig = $this->generateDovecotConfig($email);  
        Storage::disk('dovecot')->put($email->email, $dovecotConfig);

        // Update Postfix configuration
        $postfixConfig = $this->generatePostfixConfig($email);
        Storage::disk('postfix')->put($email->email, $postfixConfig);

        // Restart Dovecot and Postfix containers  
        $this->restartContainers();

        return $email;
    }

    protected function handleRecordDeletion(Email $email)
    {
        // Remove Dovecot configuration
        Storage::disk('dovecot')->delete($email->email);

        // Remove Postfix configuration  
        Storage::disk('postfix')->delete($email->email);

        // Restart Dovecot and Postfix containers
        $this->restartContainers();

        $email->delete();
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