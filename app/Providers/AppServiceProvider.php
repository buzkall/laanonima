<?php

namespace App\Providers;

use App\Actions\Books\SyncCoverColor;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegistrationResponse;
use App\Support\BookMetadata\BookMetadataProvider;
use App\Support\BookMetadata\ChainedBookMetadataProvider;
use App\Support\BookMetadata\GoogleBooksProvider;
use App\Support\BookMetadata\OpenLibraryProvider;
use App\Support\Cupida\CupidaCatalogue;
use Carbon\CarbonImmutable;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    public const string DATE_FORMAT = 'd/m/Y';
    public const string DATE_TIME_FORMAT = 'd/m/Y H:i';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(RegistrationResponseContract::class, RegistrationResponse::class);

        $this->registerBookMetadataProvider();

        /*
         | La Cupida reads three JSON files off disk and scores the whole pool
         | against every set of answers, so it is a singleton to keep that to
         | one decode per request rather than one per call.
         */
        $this->app->singleton(CupidaCatalogue::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->syncBookCoverColors();
    }

    /**
     * Fill books.cover_color from the cover, for a book that has no colour yet.
     *
     * It cannot be done in the model: media library attaches a cover after the
     * book row is written, so a saving hook only ever sees the state before the
     * cover arrived.
     *
     * MediaHasBeenAddedEvent rather than Media::created, because the row is
     * inserted before the file is copied to the disk and there would be nothing
     * to read.
     *
     * Reordering used to be a third trigger, and is not one any more: a stored
     * colour is never written over, so dragging an image to the front cannot
     * change it. The listener could only ever have fired for a book whose
     * colour had been emptied by hand, and unreliably at that -- setNewOrder
     * writes one row at a time and each write raises its own event, so the
     * first of them reads the collection while two images still share an
     * order_column. Reading a specific cover's colour is what the "read it from
     * the cover again" action on the form is for.
     */
    protected function syncBookCoverColors(): void
    {
        $sync = function(Media $media): void {
            $syncCoverColor = app(SyncCoverColor::class);
            $book = $syncCoverColor->bookFor($media);

            if ($book !== null) {
                $syncCoverColor($book);
            }
        };

        Event::listen(MediaHasBeenAddedEvent::class, function(MediaHasBeenAddedEvent $event) use ($sync): void {
            $sync($event->media);
        });

        Media::deleted($sync);
    }

    /**
     * Resolve the metadata sources named in config/books.php into one chain.
     *
     * Adding DILVE later is a new entry in this map plus a line of config.
     */
    protected function registerBookMetadataProvider(): void
    {
        $this->app->singleton(function(): BookMetadataProvider {
            $available = [
                'open_library' => OpenLibraryProvider::class,
                'google_books' => GoogleBooksProvider::class,
            ];

            $providers = array_map(
                fn(string $name): BookMetadataProvider => $this->app->make($available[$name]),
                array_values(array_filter(
                    config('books.metadata.providers', []),
                    fn(string $name): bool => isset($available[$name]),
                )),
            );

            return new ChainedBookMetadataProvider($providers);
        });
    }

    /**
     * Every date Filament renders reads d/m/Y, set once instead of per field.
     *
     * `date()`, `dateTime()` and a non-native picker all fall back to these
     * defaults, so a column or field only spells out a format when it wants to
     * differ from the house one.
     */
    protected function configureDateDisplayFormats(): void
    {
        Table::configureUsing(fn(Table $table): Table => $table
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        Schema::configureUsing(fn(Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        DateTimePicker::configureUsing(fn(DateTimePicker $picker): DateTimePicker => $picker
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));
    }

    /**
     * House defaults for every table, set once instead of per resource.
     *
     * `deferFilters(false)` applies a filter the moment it changes, dropping
     * Filament's "Apply" button; `striped()` alternates the row background so
     * long listings stay readable; 25 rows a page instead of Filament's 10,
     * so a catalogue of a few dozen titles is one or two pages, not five.
     */
    protected function configureTableDefaults(): void
    {
        Table::configureUsing(fn(Table $table): Table => $table
            ->deferFilters(false)
            ->striped()
            ->defaultPaginationPageOption(25));
    }

    /**
     * Every ternary filter is a row of three buttons rather than a select.
     *
     * The labels are read off the filter itself -- `getPlaceholder()` is the "-"
     * Filament already puts on the blank option, `getTrueLabel()` and
     * `getFalseLabel()` the "Yes" and "No" -- so a filter that spells its own
     * out with `->placeholder()` or `->trueLabel()` keeps them, and none of this
     * needs a translation key of its own.
     *
     * Three things are load-bearing. The field has to be called `value`: that is
     * the state path `TernaryFilter::queries()` reads. Blank is the `''` option --
     * `blank('')` is what sends the query down the third branch and keeps the
     * filter out of the indicators. And `->default('')` is what lights that
     * button on a page that arrives with no filter set, because the filter's own
     * state is `null` there while the option's value is `''`.
     */
    protected function configureTernaryFilters(): void
    {
        TernaryFilter::configureUsing(fn(TernaryFilter $filter): TernaryFilter => $filter
            ->schema(fn(): array => [
                ToggleButtons::make('value')
                    ->label($filter->getLabel())
                    ->grouped()
                    ->options([
                        ''  => $filter->getPlaceholder(),
                        '1' => $filter->getTrueLabel(),
                        '0' => $filter->getFalseLabel(),
                    ])
                    ->colors([
                        '1' => 'success',
                        '0' => 'gray',
                    ])
                    ->default(''),
            ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $this->configureDateDisplayFormats();
        $this->configureTableDefaults();
        $this->configureTernaryFilters();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
