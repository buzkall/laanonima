<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Enums\BookAvailability;
use App\Enums\BookBinding;
use App\Enums\BookLanguage;
use App\Enums\ContributorRole;
use App\Filament\Resources\Books\Actions\DownloadCoverAction;
use App\Filament\Resources\Books\Actions\LookupIsbnAction;
use App\Filament\Resources\Books\Actions\ResetCoverColorAction;
use App\Models\Book;
use App\Rules\Isbn;
use App\Support\Isbn as IsbnHelper;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookForm
{
    /**
     * Cataloguing on the left, selling on the right.
     *
     * The bookseller fills the record top to bottom in the wide column while
     * price, stock and availability stay in view beside it, so the commercial
     * decision never costs a scroll back up.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                self::identificationSection(),
                                self::recordSection(),
                                self::contentSection(),
                            ])
                            ->columnSpan(2),
                        Grid::make(1)
                            ->schema([
                                self::commercialSection(),
                                self::editionSection(),
                                self::physicalSection(),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),

                ...self::provenanceFields(),
            ]);
    }

    /**
     * The provenance the lookup action writes, and nothing renders.
     *
     * Filament prunes form state down to the keys its components validate, so a
     * $set() on a path with no component of its own never reaches the record.
     * These three exist purely so the lookup's bookkeeping survives the save --
     * cover_source_url especially, since AttachBookCover reads it back off the
     * saved record to fetch the cover.
     *
     * @return array<int, Hidden>
     */
    private static function provenanceFields(): array
    {
        return [
            Hidden::make('metadata_source'),
            Hidden::make('metadata_synced_at'),
            Hidden::make('cover_source_url'),
        ];
    }

    private static function identificationSection(): Section
    {
        return Section::make(__('books.sections.identification'))
            ->schema([
                TextInput::make('isbn13')
                    ->label(__('books.fields.isbn13'))
                    ->helperText(__('books.hints.isbn13'))
                    ->required()
                    ->rule(new Isbn)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn(?string $state): ?string => IsbnHelper::toIsbn13($state))
                    ->suffixAction(LookupIsbnAction::make()),

                TextInput::make('isbn10')
                    ->label(__('books.fields.isbn10'))
                    ->maxLength(10),

                TextInput::make('slug')
                    ->label(__('books.fields.slug'))
                    ->helperText(__('books.hints.slug'))
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('external_reference')
                    ->label(__('books.fields.external_reference'))
                    ->maxLength(255),
            ])
            ->columns();
    }

    private static function recordSection(): Section
    {
        return Section::make(__('books.sections.record'))
            ->schema([
                TextInput::make('title')
                    ->label(__('books.fields.title'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('subtitle')
                    ->label(__('books.fields.subtitle'))
                    ->maxLength(255),

                TextInput::make('original_title')
                    ->label(__('books.fields.original_title'))
                    ->maxLength(255),

                Repeater::make('contributors')
                    ->label(__('books.fields.contributors'))
                    ->table([
                        TableColumn::make(__('books.fields.contributor_name'))
                            ->markAsRequired(),
                        TableColumn::make(__('books.fields.contributor_role'))
                            ->markAsRequired()
                            ->width('12rem'),
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->label(__('books.fields.contributor_name'))
                            ->required(),
                        Select::make('role')
                            ->label(__('books.fields.contributor_role'))
                            ->options(ContributorRole::class)
                            ->default(ContributorRole::Autor->value)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->reorderable()
                    ->columnSpanFull(),

                Select::make('publisher_id')
                    ->label(__('books.fields.publisher_id'))
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label(__('books.publisher.label'))
                            ->required()
                            ->maxLength(255),
                    ]),

                TextInput::make('imprint')
                    ->label(__('books.fields.imprint'))
                    ->maxLength(255),

                TextInput::make('collection_name')
                    ->label(__('books.fields.collection_name'))
                    ->maxLength(255),

                TextInput::make('collection_number')
                    ->label(__('books.fields.collection_number'))
                    ->maxLength(255),
            ])
            ->columns(2);
    }

    private static function editionSection(): Section
    {
        return Section::make(__('books.sections.edition'))
            ->schema([
                DatePicker::make('published_on')
                    ->label(__('books.fields.published_on'))
                    ->native(false),
                Grid::make(2)
                    ->schema([
                        TextInput::make('published_year')
                            ->label(__('books.fields.published_year'))
                            ->numeric()
                            ->minValue(1400)
                            ->maxValue((int)now()->addYear()->format('Y')),

                        TextInput::make('edition_number')
                            ->label(__('books.fields.edition_number'))
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->columnSpanFull(),
                TextInput::make('edition_statement')
                    ->label(__('books.fields.edition_statement'))
                    ->maxLength(255),

                Grid::make()
                    ->schema([
                        Select::make('language')
                            ->label(__('books.fields.language'))
                            ->options(BookLanguage::class)
                            ->default(BookLanguage::Spa->value)
                            ->required(),

                        Select::make('original_language')
                            ->label(__('books.fields.original_language'))
                            ->options(BookLanguage::class),
                    ])
                    ->columnSpanFull(),

                Grid::make()
                    ->schema([
                        TextInput::make('country_of_publication')
                            ->label(__('books.fields.country_of_publication'))
                            ->default('ES')
                            ->required()
                            ->maxLength(2),

                        TextInput::make('city_of_publication')
                            ->label(__('books.fields.city_of_publication'))
                            ->maxLength(255),
                    ])
                    ->columnSpanFull(),

                TextInput::make('legal_deposit')
                    ->label(__('books.fields.legal_deposit'))
                    ->maxLength(255),
            ])
            ->columns(1);
    }

    private static function physicalSection(): Section
    {
        return Section::make(__('books.sections.physical'))
            ->schema([
                Select::make('binding')
                    ->label(__('books.fields.binding'))
                    ->options(BookBinding::class),

                TextInput::make('pages')
                    ->label(__('books.fields.pages'))
                    ->numeric()
                    ->minValue(1),

                Grid::make()
                    ->schema([
                        TextInput::make('height_mm')
                            ->label(__('books.fields.height_mm'))
                            ->numeric(),

                        TextInput::make('width_mm')
                            ->label(__('books.fields.width_mm'))
                            ->numeric(),

                        TextInput::make('thickness_mm')
                            ->label(__('books.fields.thickness_mm'))
                            ->numeric(),

                        TextInput::make('weight_grams')
                            ->label(__('books.fields.weight_grams'))
                            ->numeric(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    private static function contentSection(): Section
    {
        return Section::make(__('books.sections.content'))
            ->schema([
                SpatieMediaLibraryFileUpload::make('covers')
                    ->label(__('books.fields.covers'))
                    ->helperText(__('books.hints.covers'))
                    ->collection(Book::COVERS_COLLECTION)
                    ->conversion('thumb')
                    /* Filament would otherwise upload to FILESYSTEM_DISK, leaving
                       uploaded images somewhere other than downloaded covers. */
                    ->disk(config('media-library.disk_name'))
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->openable()
                    ->imageEditor()
                    ->hintAction(DownloadCoverAction::make())
                    ->columnSpanFull(),

                /*
                 | Whatever stands here wins: SyncCoverColor only fills the
                 | column while it is empty, so a colour typed in survives every
                 | later upload. Clear the field to have the cover read again,
                 | or press the hint action to read it now.
                 |
                 | The state is normalized on the way out because CoverPalette
                 | only accepts lowercase "#rrggbb" and silently falls back to
                 | the reference red for anything else.
                 */
                ColorPicker::make('cover_color')
                    ->label(__('books.fields.cover_color'))
                    ->helperText(__('books.hints.cover_color'))
                    ->rule('regex:/^#[0-9a-fA-F]{6}$/')
                    ->validationMessages(['regex' => __('books.cover_color.invalid')])
                    ->dehydrateStateUsing(fn(?string $state): ?string => blank($state) ? null : mb_strtolower(trim($state)))
                    ->hintAction(ResetCoverColorAction::make())
                    ->columnSpanFull(),

                Textarea::make('synopsis')
                    ->label(__('books.fields.synopsis'))
                    ->rows(6)
                    ->columnSpanFull(),

                Textarea::make('back_cover_text')
                    ->label(__('books.fields.back_cover_text'))
                    ->rows(4)
                    ->columnSpanFull(),

                Repeater::make('subjects')
                    ->label(__('books.fields.subjects'))
                    ->table([
                        TableColumn::make(__('books.fields.subject_scheme'))
                            ->width('10rem'),
                        TableColumn::make(__('books.fields.subject_code'))
                            ->width('10rem'),
                        TableColumn::make(__('books.fields.subject_heading'))
                            ->markAsRequired(),
                    ])
                    ->schema([
                        TextInput::make('scheme')
                            ->label(__('books.fields.subject_scheme'))
                            ->default('text'),
                        TextInput::make('code')
                            ->label(__('books.fields.subject_code')),
                        TextInput::make('heading')
                            ->label(__('books.fields.subject_heading'))
                            ->required(),
                    ])
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    private static function commercialSection(): Section
    {
        return Section::make(__('books.sections.commercial'))
            ->schema([
                TextInput::make('price_cents')
                    ->label(__('books.fields.price_cents'))
                    ->helperText(__('books.hints.price_cents'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('€')
                    ->formatStateUsing(fn(?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn(?string $state): ?int => blank($state) ? null : (int)round((float)$state * 100)),

                TextInput::make('vat_rate')
                    ->label(__('books.fields.vat_rate'))
                    ->numeric()
                    ->default(4)
                    ->suffix('%')
                    ->required(),

                TextInput::make('stock')
                    ->label(__('books.fields.stock'))
                    ->numeric()
                    ->default(0)
                    ->required(),

                Select::make('availability')
                    ->label(__('books.fields.availability'))
                    ->options(BookAvailability::class)
                    ->default(BookAvailability::Disponible->value)
                    ->required(),

                Toggle::make('is_featured')
                    ->label(__('books.fields.is_featured')),

                Toggle::make('is_active')
                    ->label(__('books.fields.is_active'))
                    ->default(true),
            ])
            ->columns(1);
    }
}
