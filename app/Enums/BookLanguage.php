<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Idioma. ISO 639-2/B codes, as emitted by DILVE in ONIX LanguageCode.
 */
enum BookLanguage: string implements HasLabel
{
    case Spa = 'spa';
    case Cat = 'cat';
    case Eus = 'eus';
    case Glg = 'glg';
    case Eng = 'eng';
    case Fra = 'fra';
    case Por = 'por';
    case Ita = 'ita';
    case Deu = 'deu';

    public function getLabel(): string
    {
        return __("books.language.{$this->value}");
    }

    /**
     * Resolve from the two-letter ISO 639-1 code the free APIs return.
     */
    public static function fromIso6391(?string $code): ?self
    {
        return match ($code) {
            'es'    => self::Spa,
            'ca'    => self::Cat,
            'eu'    => self::Eus,
            'gl'    => self::Glg,
            'en'    => self::Eng,
            'fr'    => self::Fra,
            'pt'    => self::Por,
            'it'    => self::Ita,
            'de'    => self::Deu,
            default => null,
        };
    }
}
