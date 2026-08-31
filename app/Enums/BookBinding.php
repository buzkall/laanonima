<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Encuadernación. Values map to ONIX 3.0 ProductForm codes.
 */
enum BookBinding: string implements HasLabel
{
    case Paperback = 'paperback';
    case Hardback = 'hardback';
    case Pocket = 'pocket';
    case BoardBook = 'board_book';
    case Spiral = 'spiral';
    case Ebook = 'ebook';
    case Audiobook = 'audiobook';

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
            self::Paperback, self::Pocket => 'BC',
            self::Hardback                => 'BB',
            self::BoardBook               => 'BH',
            self::Spiral                  => 'BE',
            self::Ebook                   => 'ED',
            self::Audiobook               => 'AJ',
        };
    }

    public static function fromOnixProductForm(?string $code): ?self
    {
        return match ($code) {
            'BB', 'BG'       => self::Hardback,
            'BC', 'BP'       => self::Paperback,
            'BH'             => self::BoardBook,
            'BE'             => self::Spiral,
            'ED', 'EA', 'EB' => self::Ebook,
            'AJ', 'AC', 'AB' => self::Audiobook,
            default          => null,
        };
    }
}
