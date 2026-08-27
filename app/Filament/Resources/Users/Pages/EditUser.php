<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Arzcode\FilamentMagicLogin\Actions\SendMagicLinkAction;

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
