<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Models\BookRequest;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * What readers have asked for lately, on the admin dashboard.
 *
 * The five newest, whatever their state, because the question the dashboard
 * answers is "what has come in" rather than "what is outstanding" -- the count
 * of what is still open is one card up, in {@see CatalogueStats}, and the
 * listing itself is a click away in the header action.
 *
 * Deliberately not the resource's table: no filters, no search, no bulk
 * actions, five rows and no pager. A dashboard is read at a glance and acted on
 * elsewhere, so every row opens the request and that is the only action here.
 */
class LatestBookRequests extends TableWidget
{
    /* Under the counts, across the whole dashboard: rows need the width. */
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('widgets.requests.heading'))
            ->query(fn(): Builder => BookRequest::query()
                ->with(['user', 'book'])
                ->latest()
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading(__('widgets.requests.empty'))
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown)
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('book_requests.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->tooltip(fn(BookRequest $record): string => $record->created_at?->translatedFormat('d/m/Y H:i') ?? '')
                    ->sortable(false),

                TextColumn::make('title')
                    ->label(__('book_requests.fields.title'))
                    ->description(fn(BookRequest $record): ?string => $record->author)
                    ->sortable(false)
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label(__('book_requests.fields.user_id'))
                    ->description(fn(BookRequest $record): string => $record->user->email)
                    ->sortable(false),

                TextColumn::make('status')
                    ->label(__('book_requests.fields.status'))
                    ->badge()
                    ->sortable(false),
            ])
            ->headerActions([
                Action::make('all')
                    ->label(__('widgets.requests.all'))
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->iconPosition('after')
                    ->link()
                    ->url(BookRequestResource::getUrl()),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('widgets.requests.open'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->iconButton()
                    ->url(fn(BookRequest $record): string => BookRequestResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
