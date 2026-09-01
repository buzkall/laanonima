<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\BookRequestStatus;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Models\BookRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * What this reader has asked us for, underneath their account.
 *
 * A listing and nothing more: a request is edited in `BookRequestResource`,
 * where the bookseller has the whole form -- status, internal notes, the
 * catalogue link -- rather than a modal that would only show half of it. The
 * row action therefore leaves for that page instead of opening one here.
 */
class BookRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookRequests';
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedInboxArrowDown;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('book_requests.resource.plural_label');
    }

    /**
     * Only readers ask for books. An administrator's account has no requests to
     * show and never will, so the tab is not there at all.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && ! $ownerRecord->isBookseller();
    }

    /**
     * How many of this reader's requests are still waiting on us, mirroring the
     * badge the resource puts on the sidebar.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! $ownerRecord instanceof User) {
            return null;
        }

        $open = $ownerRecord->bookRequests()->open()->count();

        return $open > 0 ? (string)$open : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('book_requests.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->tooltip(fn(BookRequest $record): string => $record->created_at?->translatedFormat('d/m/Y H:i') ?? '')
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('book_requests.fields.title'))
                    ->description(fn(BookRequest $record): ?string => $record->author)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('book_requests.fields.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('book.title')
                    ->label(__('book_requests.fields.book_id'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('isbn')
                    ->label(__('book_requests.fields.isbn'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('book_requests.fields.status'))
                    ->options(BookRequestStatus::class)
                    ->multiple(),
            ])
            ->emptyStateHeading(__('user.relations.book_requests.empty'))
            ->emptyStateDescription(__('user.relations.book_requests.empty_hint'))
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->url(fn(BookRequest $record): string => BookRequestResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
