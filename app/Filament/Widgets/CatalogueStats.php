<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Cupida\CupidaResource;
use App\Filament\Resources\Publishers\PublisherResource;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\CupidaRecommendation;
use App\Models\Publisher;
use App\Models\Subject;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * How much of everything the shop holds, on the admin dashboard.
 *
 * Every stat is a whole count with the part that matters underneath it: a
 * catalogue of 400 books of which 380 are on the web is a different shop from
 * one where half of them are hidden, and the second number is the one a
 * bookseller acts on. Each card opens the listing it counts, so the dashboard
 * is a way in rather than a report.
 *
 * Materias is the one card that reads the other way round, because THEMA is a
 * scheme rather than a holding: three thousand codes are seeded from a file and
 * belong to nobody, so what the shop has is the handful a book is actually filed
 * under and the scheme is the small print. It is also the one card with nowhere
 * to go -- subjects have no resource of their own.
 */
class CatalogueStats extends StatsOverviewWidget
{
    /* After the account widget, which greets whoever just signed in. */
    protected static ?int $sort = 1;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make(__('widgets.catalogue.books'), $this->formatted(Book::count()))
                ->description($this->counted('widgets.catalogue.books_online', Book::query()->active()->count()))
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('primary')
                ->url(BookResource::getUrl()),

            Stat::make(__('widgets.catalogue.authors'), $this->formatted(Author::count()))
                ->description($this->counted('widgets.catalogue.authors_with_books', Author::query()->has('books')->count()))
                ->descriptionIcon(Heroicon::OutlinedBookOpen)
                ->icon(Heroicon::OutlinedUsers)
                ->url(AuthorResource::getUrl()),

            Stat::make(__('widgets.catalogue.publishers'), $this->formatted(Publisher::count()))
                ->description($this->counted('widgets.catalogue.publishers_with_books', Publisher::query()->has('books')->count()))
                ->descriptionIcon(Heroicon::OutlinedBookOpen)
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->url(PublisherResource::getUrl()),

            Stat::make(__('widgets.catalogue.subjects'), $this->formatted(Subject::query()->has('books')->count()))
                ->description(__('widgets.catalogue.subjects_scheme', ['count' => $this->formatted(Subject::count())]))
                ->descriptionIcon(Heroicon::OutlinedTag)
                ->icon(Heroicon::OutlinedRectangleStack),

            Stat::make(__('widgets.catalogue.requests'), $this->formatted(BookRequest::count()))
                ->description($this->counted('widgets.catalogue.requests_open', BookRequest::query()->open()->count()))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('warning')
                ->url(BookRequestResource::getUrl()),

            Stat::make(__('widgets.catalogue.recommendations'), $this->formatted(CupidaRecommendation::count()))
                ->description($this->counted('widgets.catalogue.recommendations_month', CupidaRecommendation::query()->where('created_at', '>=', now()->subDays(30))->count()))
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->icon(Heroicon::OutlinedSparkles)
                ->url(CupidaResource::getUrl()),
        ];
    }

    /**
     * A count and the line that reads it, so the plural is decided by the number
     * rather than by whoever wrote the string. `__()` returns the whole
     * pipe-separated line; only `trans_choice()` picks a branch of it.
     */
    protected function counted(string $key, int $count): string
    {
        return trans_choice($key, $count, ['count' => $this->formatted($count)]);
    }

    /**
     * THEMA runs to a few thousand codes, so the separator is not decoration.
     * Spelled out rather than left to `Number`'s own default, which is English.
     */
    protected function formatted(int $count): string
    {
        $formatted = Number::format($count, locale: 'es');

        /* `Number` answers false when intl cannot format; the digits still read. */
        return $formatted === false ? (string)$count : $formatted;
    }
}
