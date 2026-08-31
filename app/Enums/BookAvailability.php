<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Disponibilidad. Values map to ONIX 3.0 code list 65 (ProductAvailability).
 */
enum BookAvailability: string implements HasColor, HasLabel
{
    case Available = 'available';
    case ToOrder = 'to_order';
    case OutOfStock = 'out_of_stock';
    case OutOfPrint = 'out_of_print';
    case NotYetPublished = 'not_yet_published';

    public function getLabel(): string
    {
        return __("books.availability.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available       => 'success',
            self::ToOrder         => 'warning',
            self::OutOfStock      => 'danger',
            self::OutOfPrint      => 'gray',
            self::NotYetPublished => 'info',
        };
    }

    /**
     * The ONIX 3.0 code list 65 value this availability maps to.
     */
    public function onixCode(): string
    {
        return match ($this) {
            self::Available       => '20',
            self::ToOrder         => '21',
            self::OutOfStock      => '31',
            self::OutOfPrint      => '40',
            self::NotYetPublished => '10',
        };
    }

    public static function fromOnixCode(?string $code): ?self
    {
        return match ($code) {
            '20', '11', '12'       => self::Available,
            '21', '22', '23'       => self::ToOrder,
            '30', '31', '32'       => self::OutOfStock,
            '40', '41', '42', '43' => self::OutOfPrint,
            '10'                   => self::NotYetPublished,
            default                => null,
        };
    }
}
