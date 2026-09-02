<?php

namespace App\Support\BookMetadata;

use App\Enums\BookBinding;
use App\Enums\BookLanguage;

/**
 * Everything a metadata provider could tell us about one ISBN.
 *
 * Field names deliberately match the books table columns, so the Filament form
 * and the seeder can fill themselves from toBookAttributes() without a mapping
 * layer in between. Every field is nullable: no source fills them all, and the
 * whole point is that staff correct whatever is missing.
 */
readonly class BookMetadata
{
    /**
     * @param  array<int, array{name: string, role: string}>  $contributors
     * @param  array<int, array{scheme: string, code: string|null, heading: string|null}>  $subjects
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $isbn13,
        public ?string $isbn10 = null,
        public ?string $title = null,
        public ?string $subtitle = null,
        public array $contributors = [],
        public ?string $publisherName = null,
        public ?string $imprint = null,
        public ?string $collectionName = null,
        public ?string $publishedOn = null,
        public ?int $publishedYear = null,
        public ?string $editionStatement = null,
        public ?string $legalDeposit = null,
        public ?string $cityOfPublication = null,
        public ?BookBinding $binding = null,
        public ?int $pages = null,
        public ?int $heightMm = null,
        public ?int $widthMm = null,
        public ?int $thicknessMm = null,
        public ?int $weightGrams = null,
        public ?BookLanguage $language = null,
        public array $subjects = [],
        public ?string $synopsis = null,
        public ?string $coverSourceUrl = null,
        public ?string $source = null,
        public array $raw = [],
    ) {}

    /**
     * A plain-array form, safe to put in the cache.
     *
     * config/cache.php deliberately forbids unserializing classes from the
     * cache ("serializable_classes" => false) to blunt gadget-chain attacks, so
     * the DTO itself must never be cached.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isbn13'            => $this->isbn13,
            'isbn10'            => $this->isbn10,
            'title'             => $this->title,
            'subtitle'          => $this->subtitle,
            'contributors'      => $this->contributors,
            'publisherName'     => $this->publisherName,
            'imprint'           => $this->imprint,
            'collectionName'    => $this->collectionName,
            'publishedOn'       => $this->publishedOn,
            'publishedYear'     => $this->publishedYear,
            'editionStatement'  => $this->editionStatement,
            'legalDeposit'      => $this->legalDeposit,
            'cityOfPublication' => $this->cityOfPublication,
            'binding'           => $this->binding?->value,
            'pages'             => $this->pages,
            'heightMm'          => $this->heightMm,
            'widthMm'           => $this->widthMm,
            'thicknessMm'       => $this->thicknessMm,
            'weightGrams'       => $this->weightGrams,
            'language'          => $this->language?->value,
            'subjects'          => $this->subjects,
            'synopsis'          => $this->synopsis,
            'coverSourceUrl'    => $this->coverSourceUrl,
            'source'            => $this->source,
            'raw'               => $this->raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isbn13: $data['isbn13'],
            isbn10: $data['isbn10'] ?? null,
            title: $data['title'] ?? null,
            subtitle: $data['subtitle'] ?? null,
            contributors: $data['contributors'] ?? [],
            publisherName: $data['publisherName'] ?? null,
            imprint: $data['imprint'] ?? null,
            collectionName: $data['collectionName'] ?? null,
            publishedOn: $data['publishedOn'] ?? null,
            publishedYear: $data['publishedYear'] ?? null,
            editionStatement: $data['editionStatement'] ?? null,
            legalDeposit: $data['legalDeposit'] ?? null,
            cityOfPublication: $data['cityOfPublication'] ?? null,
            binding: BookBinding::tryFrom($data['binding'] ?? ''),
            pages: $data['pages'] ?? null,
            heightMm: $data['heightMm'] ?? null,
            widthMm: $data['widthMm'] ?? null,
            thicknessMm: $data['thicknessMm'] ?? null,
            weightGrams: $data['weightGrams'] ?? null,
            language: BookLanguage::tryFrom($data['language'] ?? ''),
            subjects: $data['subjects'] ?? [],
            synopsis: $data['synopsis'] ?? null,
            coverSourceUrl: $data['coverSourceUrl'] ?? null,
            source: $data['source'] ?? null,
            raw: $data['raw'] ?? [],
        );
    }

    /**
     * Fill this record's gaps from another provider's result.
     *
     * Values already present win: providers are consulted in priority order, so
     * whoever answered first is the more trusted source for that field.
     */
    public function merge(self $other): self
    {
        return new self(
            isbn13: $this->isbn13,
            isbn10: $this->isbn10 ?? $other->isbn10,
            title: $this->title ?? $other->title,
            subtitle: $this->subtitle ?? $other->subtitle,
            contributors: $this->contributors !== [] ? $this->contributors : $other->contributors,
            publisherName: $this->publisherName ?? $other->publisherName,
            imprint: $this->imprint ?? $other->imprint,
            collectionName: $this->collectionName ?? $other->collectionName,
            publishedOn: $this->publishedOn ?? $other->publishedOn,
            publishedYear: $this->publishedYear ?? $other->publishedYear,
            editionStatement: $this->editionStatement ?? $other->editionStatement,
            legalDeposit: $this->legalDeposit ?? $other->legalDeposit,
            cityOfPublication: $this->cityOfPublication ?? $other->cityOfPublication,
            binding: $this->binding ?? $other->binding,
            pages: $this->pages ?? $other->pages,
            heightMm: $this->heightMm ?? $other->heightMm,
            widthMm: $this->widthMm ?? $other->widthMm,
            thicknessMm: $this->thicknessMm ?? $other->thicknessMm,
            weightGrams: $this->weightGrams ?? $other->weightGrams,
            language: $this->language ?? $other->language,
            subjects: $this->subjects !== [] ? $this->subjects : $other->subjects,
            synopsis: $this->synopsis ?? $other->synopsis,
            coverSourceUrl: $this->coverSourceUrl ?? $other->coverSourceUrl,
            source: implode('+', array_filter([$this->source, $other->source])),
            raw: [...$other->raw, ...$this->raw],
        );
    }

    /**
     * The non-null subset, keyed by books table column.
     *
     * Nulls are stripped so that filling a Filament form never blanks a field
     * the bookseller has already typed by hand.
     *
     * @return array<string, mixed>
     */
    public function toBookAttributes(): array
    {
        return array_filter([
            'isbn13'              => $this->isbn13,
            'isbn10'              => $this->isbn10,
            'title'               => $this->title,
            'subtitle'            => $this->subtitle,
            'imprint'             => $this->imprint,
            'collection_name'     => $this->collectionName,
            'published_on'        => $this->publishedOn,
            'published_year'      => $this->publishedYear,
            'edition_statement'   => $this->editionStatement,
            'legal_deposit'       => $this->legalDeposit,
            'city_of_publication' => $this->cityOfPublication,
            'binding'             => $this->binding?->value,
            'pages'               => $this->pages,
            'height_mm'           => $this->heightMm,
            'width_mm'            => $this->widthMm,
            'thickness_mm'        => $this->thicknessMm,
            'weight_grams'        => $this->weightGrams,
            'language'            => $this->language?->value,
            'subjects'            => $this->subjects === [] ? null : $this->subjects,
            'synopsis'            => $this->synopsis,
            'cover_source_url'    => $this->coverSourceUrl,
            'metadata_source'     => $this->source,
        ], fn(mixed $value): bool => $value !== null);
    }
}
