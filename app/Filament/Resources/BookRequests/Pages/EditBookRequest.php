<?php

namespace App\Filament\Resources\BookRequests\Pages;

use App\Filament\Resources\BookRequests\BookRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBookRequest extends EditRecord
{
    protected static string $resource = BookRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
