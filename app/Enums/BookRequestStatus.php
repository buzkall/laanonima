<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * How far along a bookseller is with an order a reader asked for.
 */
enum BookRequestStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pendiente = 'pendiente';
    case EnCurso = 'en_curso';
    case Conseguido = 'conseguido';
    case Descartado = 'descartado';

    public function getLabel(): string
    {
        return __("book_requests.status.{$this->value}");
    }

    /**
     * The statuses a bookseller could still be acting on.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Pendiente, self::EnCurso];
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente  => 'warning',
            self::EnCurso    => 'info',
            self::Conseguido => 'success',
            self::Descartado => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Pendiente  => Heroicon::OutlinedInbox,
            self::EnCurso    => Heroicon::OutlinedTruck,
            self::Conseguido => Heroicon::OutlinedCheckCircle,
            self::Descartado => Heroicon::OutlinedXCircle,
        };
    }
}
