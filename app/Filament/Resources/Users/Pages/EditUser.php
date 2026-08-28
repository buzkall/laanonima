<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Arzcode\FilamentMagicLogin\Actions\SendMagicLinkAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendMagicLinkAction::make(),
            DeleteAction::make(),
        ];
    }
}
