<?php

namespace App\Filament\App\Resources\Files\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\App\Resources\Files\FileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFile extends EditRecord
{
    protected static string $resource = FileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}