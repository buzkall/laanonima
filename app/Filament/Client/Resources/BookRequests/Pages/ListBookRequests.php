<?php

namespace App\Filament\Client\Resources\BookRequests\Pages;

use App\Filament\Client\Resources\BookRequests\BookRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBookRequests extends ListRecords
{
    protected static string $resource = BookRequestResource::class;

    /**
     * Asking for another book happens on the shop, not in here, so the only
     * button on the page leaves the panel for the form.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ask')
                ->label(__('book_requests.client.ask'))
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->url(route('book-requests.create')),
        ];
    }
}
