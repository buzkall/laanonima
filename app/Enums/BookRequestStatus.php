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
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Obtained = 'obtained';
    case Dropped = 'dropped';

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
        return [self::Pending, self::InProgress];
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending    => 'warning',
            self::InProgress => 'info',
            self::Obtained   => 'success',
            self::Dropped    => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Pending    => Heroicon::OutlinedInbox,
            self::InProgress => Heroicon::OutlinedTruck,
            self::Obtained   => Heroicon::OutlinedCheckCircle,
            self::Dropped    => Heroicon::OutlinedXCircle,
        };
    }
}
