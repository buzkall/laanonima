<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Encuadernación. Values map to ONIX 3.0 ProductForm codes.
 */
enum BookBinding: string implements HasLabel
{
    case Rustica = 'rustica';
    case TapaDura = 'tapa_dura';
    case Bolsillo = 'bolsillo';
    case Carton = 'carton';
    case Espiral = 'espiral';
    case Ebook = 'ebook';
    case Audiolibro = 'audiolibro';

    public function getLabel(): string
    {
        return __("books.binding.{$this->value}");
    }

    /**
     * The ONIX 3.0 ProductForm code this binding maps to.
     */
    public function onixProductForm(): string
    {
        return match ($this) {
            self::Rustica, self::Bolsillo => 'BC',
            self::TapaDura                => 'BB',
            self::Carton                  => 'BH',
            self::Espiral                 => 'BE',
            self::Ebook                   => 'ED',
            self::Audiolibro              => 'AJ',
        };
    }

    public static function fromOnixProductForm(?string $code): ?self
    {
        return match ($code) {
            'BB', 'BG'       => self::TapaDura,
            'BC', 'BP'       => self::Rustica,
            'BH'             => self::Carton,
            'BE'             => self::Espiral,
            'ED', 'EA', 'EB' => self::Ebook,
            'AJ', 'AC', 'AB' => self::Audiolibro,
            default          => null,
        };
    }
}
