<?php

namespace App\Filament\Resources\Cupida\Tables;

use App\Models\CupidaRecommendation;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What people asked La Cupida for, and what it said.
 *
 * Laid out as cards rather than as rows, because the thing being logged is a
 * book: the cover is what makes a page of these readable at a glance, and a
 * pitch is a paragraph that a table cell can only ever truncate. Each card is
 * one session -- the book on the left, what was said about it on the right, and
 * underneath it the answers that got the reader there, as badges.
 *
 * Read-only by design, and enforced by CupidaRecommendationPolicy rather than
 * by leaving actions off: a recommendation is something that happened, and what
 * the shop spent and what it recommended is the only record of either.
 *
 * The answers are shown as labels rather than as the "kind:key" they are
 * stored as -- "Fantasía", not "theme:FM" -- and both of those columns are
 * computed, so they carry an explicit `sortable(false)`.
 *
 * Nothing is sortable by column, and that is deliberate: the sort control lives
 * in the filter panel with everything else the page asks, as a `sort` filter.
 *
 * The cost column is the only one that answers a question about the shop rather
 * than about a reader, and it is really the footer that answers it: a single
 * recommendation costs a fraction of a cent, so the number worth reading is the
 * sum under whatever the filters have left on screen.
 */
class CupidaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        ImageColumn::make('cover')
                            ->label(__('cupida.admin.fields.cover'))
                            ->state(fn(CupidaRecommendation $record): string => $record->coverUrl())
                            ->imageHeight(112)
                            /* The panel ships a compiled stylesheet built from
                               Filament's own markup, so a Tailwind class put on
                               this image is a class that does not exist. The
                               height is an inline style and works; anything
                               else has to come from the column's own API. */
                            ->extraImgAttributes(['loading' => 'lazy'])
                            ->grow(false),

                        Stack::make([
                            TextColumn::make('title')
                                ->label(__('cupida.admin.fields.book'))
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->url(fn(CupidaRecommendation $record): ?string => $record->book
                                    ? route('books.show', $record->book)
                                    : null)
                                ->openUrlInNewTab()
                                /* The arrow is the only thing that says this
                                   one is in our catalog too, now that the
                                   title is the link to it. */
                                ->icon(fn(CupidaRecommendation $record): ?Heroicon => $record->book
                                    ? Heroicon::ArrowTopRightOnSquare
                                    : null)
                                ->iconPosition(IconPosition::After)
                                ->iconColor('gray')
                                ->searchable(['title', 'author', 'ean'])
                                ->wrap(),

                            /* The author reads left and the EAN right, the way
                               a spine and a barcode sit on the book itself. The
                               EAN is the shop's own address for it -- ours, when
                               we catalog it too, is what the title links to --
                               and it goes unlinked rather than guessed at for a
                               book the pool no longer has. */
                            Split::make([
                                TextColumn::make('author')
                                    ->label(__('cupida.admin.fields.author'))
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->wrap(),

                                TextColumn::make('ean')
                                    ->label(__('cupida.admin.fields.ean'))
                                    ->color('gray')
                                    ->size(TextSize::Small)
                                    ->url(fn(CupidaRecommendation $record): ?string => $record->shopUrl())
                                    ->openUrlInNewTab()
                                    ->alignEnd()
                                    ->searchable()
                                    ->grow(false),
                            ]),

                            TextColumn::make('pitch')
                                ->label(__('cupida.admin.fields.pitch'))
                                ->description(fn(CupidaRecommendation $record): ?string => $record->match_line)
                                ->color('gray')
                                ->limit(220)
                                ->wrap(),

                            /* What our own scoring would have handed over on
                               its own -- which is to say what this reader would
                               have been given with no model in front of it.
                               Free to keep: the shortlist is built before
                               anything is prompted.

                               On every card, including the ones where it is the
                               book already in bold above. Drawn only on the
                               cards that differ, the ones that say nothing
                               would have to be read as agreement, and a blank
                               is a poor way to say anything. */
                            TextColumn::make('shortlist_title')
                                ->label(__('cupida.admin.fields.shortlist'))
                                ->state(fn(CupidaRecommendation $record): ?string => $record->shortlistPick())
                                ->color('gray')
                                ->size(TextSize::Small)
                                ->icon(Heroicon::OutlinedQueueList)
                                ->iconColor('gray')
                                ->sortable(false)
                                ->wrap(),
                        ])->space(2),
                    ])->from('sm'),

                    /* The answers, as a bookseller reads them: a badge each.
                       Both are arrays, so the badge renders once per card the
                       reader swiped -- two dozen of them by the end of a
                       session, and all of them are drawn. A cut list needs a
                       tooltip to say what was cut, and a tooltip is something
                       nobody hovers on a phone; the badges wrap instead.

                       The two lists run one under the other and are told
                       apart by color alone, which is nothing at all to go on
                       once the passes are turned on: a heart and a cross ride
                       in front of every badge so a card reads as a card and
                       not as one long run of tags.

                       What was passed on is the longer of the two lists and the
                       less asked-for, so it is off until someone turns it on. */
                    TextColumn::make('likes')
                        ->label(__('cupida.admin.fields.likes'))
                        ->state(fn(CupidaRecommendation $record): array => $record->likeLabels())
                        ->badge()
                        ->color('primary')
                        ->icon(Heroicon::Heart)
                        ->placeholder('—')
                        ->sortable(false)
                        ->wrap(),

                    TextColumn::make('passes')
                        ->label(__('cupida.admin.fields.passes'))
                        ->state(fn(CupidaRecommendation $record): array => $record->passLabels())
                        ->badge()
                        ->color('gray')
                        ->icon(Heroicon::XMark)
                        ->placeholder('—')
                        ->sortable(false)
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->wrap(),

                    /* The footer of the card: when, whether the pitch was written, and what it cost. */
                    Split::make([
                        TextColumn::make('created_at')
                            ->label(__('cupida.admin.fields.created_at'))
                            ->dateTime()
                            ->since()
                            ->icon(Heroicon::Clock)
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->tooltip(fn(CupidaRecommendation $record): string => $record->created_at?->translatedFormat('d/m/Y H:i') ?? '')
                            ->grow(false),

                        TextColumn::make('written')
                            ->label(__('cupida.admin.fields.written'))
                            ->badge()
                            ->state(fn(CupidaRecommendation $record): string => $record->written
                                ? __('cupida.admin.written.yes')
                                : __('cupida.admin.written.no'))
                            ->color(fn(CupidaRecommendation $record): string => $record->written ? 'success' : 'gray')
                            ->tooltip(fn(CupidaRecommendation $record): ?string => $record->model)
                            ->grow(false),

                        /* What the pitch cost the shop, in the footer of every
                           card and summed under the page.

                           Empty says more than one thing and the card says
                           which: a canned line was never prompted and cost
                           nothing, while a written one whose tokens are on file
                           and whose price is not came from a model with no rate
                           in `cupida.prices` -- a list gone stale rather than a
                           free recommendation. A written line with no tokens
                           either predates any of this being kept, and a dash is
                           the honest answer for it. */
                        TextColumn::make('cost')
                            ->label(__('cupida.admin.fields.cost'))
                            ->money('USD', decimalPlaces: 4)
                            ->icon(Heroicon::Banknotes)
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->tooltip(fn(CupidaRecommendation $record): ?string => $record->tokenLabel())
                            ->placeholder(fn(CupidaRecommendation $record): string => match (true) {
                                ! $record->written             => __('cupida.admin.cost_none'),
                                $record->tokenLabel() !== null => __('cupida.admin.cost_unknown'),
                                default                        => '—',
                            })
                            ->summarize(
                                Sum::make()
                                    ->label(__('cupida.admin.fields.cost_total'))
                                    ->money('USD', decimalPlaces: 4),
                            ),

                        TextColumn::make('user.name')
                            ->label(__('cupida.admin.fields.user'))
                            ->icon(Heroicon::User)
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->placeholder(__('cupida.admin.anonymous'))
                            ->searchable()
                            ->grow(false)
                            ->toggleable(isToggledHiddenByDefault: true),
                    ])->from('sm'),
                ])->space(3),
            ])
            /* Every card asks for the reader and for the book -- the cover
               and the link to our page for it -- so both come with the page of
               rows rather than one query each. The media is what the cover is
               read out of, and is a third query on its own if it is left out. */
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['user', 'book.media']))
            /* Two to a row and no more: a card carries a cover, a paragraph
               and a run of badges, and a third column turns all three into
               columns of one word. */
            ->contentGrid(['lg' => 2])
            ->defaultSort('created_at', 'desc')
            ->filters(self::filters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4);
    }

    /**
     * Everything the page asks about a session, in one box above the cards.
     *
     * Sorting is the first of them rather than a control of its own: see
     * `sortFilter()`.
     *
     * @return array<int, BaseFilter>
     */
    private static function filters(): array
    {
        return [
            self::sortFilter(),

            /* No queries of its own: a ternary filter on a boolean column
               already asks exactly this. */
            TernaryFilter::make('written')
                ->label(__('cupida.admin.filters.written')),

            /* Both sides are scopes on the model rather than a pair of where's
               here: which rows count as agreement is a rule about the log --
               a canned line is outside both sides of it -- and not a detail of
               this table. */
            TernaryFilter::make('agreed')
                ->label(__('cupida.admin.filters.agreed'))
                ->queries(
                    true: fn(Builder $query): Builder => self::agreedWithShortlist($query),
                    false: fn(Builder $query): Builder => self::overruledShortlist($query),
                    blank: fn(Builder $query): Builder => $query,
                ),

            /* `nullable()` is whereNotNull/whereNull, which is the question:
               the EAN is one of ours when the row points at a book. */
            TernaryFilter::make('book_id')
                ->label(__('cupida.admin.filters.in_catalog'))
                ->nullable(),
        ];
    }

    /**
     * Filament hands a filter's callback a bare `Builder`, which has lost the
     * model and with it any scope on it, so the two sides of the agreement
     * filter go through here: a typed parameter is what puts the model back.
     *
     * @param  Builder<CupidaRecommendation>  $query
     * @return Builder<CupidaRecommendation>
     */
    private static function agreedWithShortlist(Builder $query): Builder
    {
        return $query->agreedWithShortlist();
    }

    /**
     * @param  Builder<CupidaRecommendation>  $query
     * @return Builder<CupidaRecommendation>
     */
    private static function overruledShortlist(Builder $query): Builder
    {
        return $query->overruledShortlist();
    }

    /**
     * How the cards are ordered, asked in the same box as the filters.
     *
     * Filament floats a sort select of its own between the search bar and the
     * first card of a content layout, and renders it for as long as one visible
     * column answers `isSortable()`. Dropping `sortable()` from every column is
     * what takes it off the page and leaves this the only thing that orders.
     *
     * It has to be `baseQuery()` and never `query()`: a filter's `query()` runs
     * inside a `where()` group, and an `orderBy` put on that nested builder is
     * thrown away along with it. The no-op `query()` is what stops SelectFilter
     * falling back to its own `where('sort', ...)` for a column no table has.
     *
     * Filters are applied before sorting, so this ordering is the primary one
     * and the table's `defaultSort` follows as the tiebreaker. Blank is
     * therefore newest first, which is what the placeholder names, and the
     * options are only the ways of departing from it.
     *
     * The value arrives from the browser, so the column is matched against the
     * four that may be ordered by before it reaches `orderBy()`.
     */
    private static function sortFilter(): SelectFilter
    {
        return SelectFilter::make('sort')
            ->label(__('cupida.admin.filters.sort'))
            ->placeholder(__('cupida.admin.sort.newest'))
            ->options([
                'created_at:asc'      => __('cupida.admin.sort.oldest'),
                'title:asc'           => __('cupida.admin.sort.title_asc'),
                'title:desc'          => __('cupida.admin.sort.title_desc'),
                'cost:desc'           => __('cupida.admin.sort.cost_desc'),
                'cost:asc'            => __('cupida.admin.sort.cost_asc'),
                'shortlist_rank:desc' => __('cupida.admin.sort.rank_desc'),
            ])
            ->query(fn(Builder $query): Builder => $query)
            ->baseQuery(function(Builder $query, array $data): Builder {
                [$column, $direction] = array_pad(
                    explode(':', (string)($data['value'] ?? '')),
                    2,
                    'asc',
                );

                return $query->when(
                    in_array($column, ['created_at', 'title', 'cost', 'shortlist_rank'], true),
                    fn(Builder $query): Builder => $query->orderBy(
                        $column,
                        $direction === 'desc' ? 'desc' : 'asc',
                    ),
                );
            });
    }
}
