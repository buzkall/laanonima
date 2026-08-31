<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Values map to ONIX 3.0 ContributorRole codes.
 */
enum ContributorRole: string implements HasLabel
{
    case Author = 'author';
    case Translator = 'translator';
    case Illustrator = 'illustrator';
    case Editor = 'editor';
    case Foreword = 'foreword';
    case Photographer = 'photographer';

    public function getLabel(): string
    {
        return __("books.contributor_role.{$this->value}");
    }

    public function onixCode(): string
    {
        return match ($this) {
            self::Author       => 'A01',
            self::Translator   => 'B06',
            self::Illustrator  => 'A12',
            self::Editor       => 'B01',
            self::Foreword     => 'A15',
            self::Photographer => 'A13',
        };
    }

    public static function fromOnixCode(?string $code): ?self
    {
        return match ($code) {
            'A01', 'A02'               => self::Author,
            'B06'                      => self::Translator,
            'A12'                      => self::Illustrator,
            'B01', 'B02'               => self::Editor,
            'A15', 'A16', 'A23', 'A24' => self::Foreword,
            'A13'                      => self::Photographer,
            default                    => null,
        };
    }
}
