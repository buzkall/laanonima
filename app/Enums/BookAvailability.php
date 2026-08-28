<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Disponibilidad. Values map to ONIX 3.0 code list 65 (ProductAvailability).
 */
enum BookAvailability: string implements HasColor, HasLabel
{
    case Disponible = 'disponible';
    case BajoPedido = 'bajo_pedido';
    case Agotado = 'agotado';
    case Descatalogado = 'descatalogado';
    case NoPublicado = 'no_publicado';

    public function getLabel(): string
    {
        return __("books.availability.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Disponible    => 'success',
            self::BajoPedido    => 'warning',
            self::Agotado       => 'danger',
            self::Descatalogado => 'gray',
            self::NoPublicado   => 'info',
        };
    }

    /**
     * The ONIX 3.0 code list 65 value this availability maps to.
     */
    public function onixCode(): string
    {
        return match ($this) {
            self::Disponible    => '20',
            self::BajoPedido    => '21',
            self::Agotado       => '31',
            self::Descatalogado => '40',
            self::NoPublicado   => '10',
        };
    }

    public static function fromOnixCode(?string $code): ?self
    {
        return match ($code) {
            '20', '11', '12'       => self::Disponible,
            '21', '22', '23'       => self::BajoPedido,
            '30', '31', '32'       => self::Agotado,
            '40', '41', '42', '43' => self::Descatalogado,
            '10'                   => self::NoPublicado,
            default                => null,
        };
    }
}
