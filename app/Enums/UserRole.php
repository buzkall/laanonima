<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum UserRole: string implements HasColor, HasIcon, HasLabel
{
    case Admin = 'admin';
    case Client = 'client';

    public function getLabel(): string
    {
        return __("user.roles.{$this->value}");
    }

    /**
     * The Filament panel this role is allowed into. Each role owns exactly one
     * panel, and no role may enter another's.
     */
    public function panelId(): string
    {
        return match ($this) {
            self::Admin  => 'admin',
            self::Client => 'client',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin  => 'warning',
            self::Client => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Admin  => Heroicon::OutlinedShieldCheck,
            self::Client => Heroicon::OutlinedUser,
        };
    }
}
