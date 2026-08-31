<?php

namespace App\Filament\Resources\BookRequests\Pages;

use App\Filament\Resources\BookRequests\BookRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBookRequests extends ListRecords
{
    protected static string $resource = BookRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
