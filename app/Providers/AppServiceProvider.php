<?php

namespace App\Providers;

use App\Actions\Books\SyncCoverColor;
use App\Http\Responses\LoginResponse;
use App\Support\BookMetadata\BookMetadataProvider;
use App\Support\BookMetadata\ChainedBookMetadataProvider;
use App\Support\BookMetadata\GoogleBooksProvider;
use App\Support\BookMetadata\OpenLibraryProvider;
use Carbon\CarbonImmutable;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
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

        $this->registerBookMetadataProvider();
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
     * Keep books.cover_color in step with the cover it is derived from.
     *
     * It cannot be done in the model: media library attaches a cover after the
     * book row is written, so a saving hook only ever sees the state before the
     * cover arrived. These three are the ways the leading image can change.
     *
     * MediaHasBeenAddedEvent rather than Media::created, because the row is
     * inserted before the file is copied to the disk and there would be nothing
     * to read. Reordering comes through as an ordinary update, filtered down to
     * order_column so that writing a conversion does not trigger a re-read.
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

        Media::updated(function(Media $media) use ($sync): void {
            if ($media->wasChanged('order_column')) {
                $sync($media);
            }
        });
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
     * long listings stay readable.
     */
    protected function configureTableDefaults(): void
    {
        Table::configureUsing(fn(Table $table): Table => $table
            ->deferFilters(false)
            ->striped());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $this->configureDateDisplayFormats();
        $this->configureTableDefaults();

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
