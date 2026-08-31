<?php

namespace App\Filament\Resources\BookRequests\Pages;

use App\Filament\Resources\BookRequests\BookRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBookRequest extends CreateRecord
{
    protected static string $resource = BookRequestResource::class;
}
