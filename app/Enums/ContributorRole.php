<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Values map to ONIX 3.0 ContributorRole codes.
 */
enum ContributorRole: string implements HasLabel
{
    case Autor = 'autor';
    case Traductor = 'traductor';
    case Ilustrador = 'ilustrador';
    case EditorLiterario = 'editor_literario';
    case Prologuista = 'prologuista';
    case Fotografo = 'fotografo';

    public function getLabel(): string
    {
        return __("books.contributor_role.{$this->value}");
    }

    public function onixCode(): string
    {
        return match ($this) {
            self::Autor           => 'A01',
            self::Traductor       => 'B06',
            self::Ilustrador      => 'A12',
            self::EditorLiterario => 'B01',
            self::Prologuista     => 'A15',
            self::Fotografo       => 'A13',
        };
    }

    public static function fromOnixCode(?string $code): ?self
    {
        return match ($code) {
            'A01', 'A02'               => self::Autor,
            'B06'                      => self::Traductor,
            'A12'                      => self::Ilustrador,
            'B01', 'B02'               => self::EditorLiterario,
            'A15', 'A16', 'A23', 'A24' => self::Prologuista,
            'A13'                      => self::Fotografo,
            default                    => null,
        };
    }
}
