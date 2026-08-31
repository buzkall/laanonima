<?php

namespace App\Filament\Resources\BookRequests\Schemas;

use App\Enums\BookRequestStatus;
use App\Models\Book;
use App\Rules\Isbn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookRequestForm
{
    /**
     * What was asked for on the left, who asked and what we did about it on the
     * right. Everything on this screen arrived from the web, so the fields are
     * editable rather than read-only: a bookseller correcting a half-remembered
     * title before ordering is the normal use of this form, and a request taken
     * over the counter is typed straight into it.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                self::bookSection(),
                            ])
                            ->columnSpan(2),
                        Grid::make(1)
                            ->schema([
                                self::readerSection(),
                                self::handlingSection(),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function bookSection(): Section
    {
        return Section::make(__('book_requests.sections.book'))
            ->schema([
                TextInput::make('title')
                    ->label(__('book_requests.fields.title'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('author')
                    ->label(__('book_requests.fields.author'))
                    ->maxLength(255),

                TextInput::make('publisher')
                    ->label(__('book_requests.fields.publisher'))
                    ->maxLength(255),

                TextInput::make('isbn')
                    ->label(__('book_requests.fields.isbn'))
                    ->rules([new Isbn])
                    ->maxLength(20),

                Select::make('book_id')
                    ->label(__('book_requests.fields.book_id'))
                    ->helperText(__('book_requests.hints.book_id'))
                    ->relationship('book', 'title')
                    ->getOptionLabelFromRecordUsing(fn(Book $record): string => $record->title)
                    ->searchable()
                    ->preload(),

                Textarea::make('notes')
                    ->label(__('book_requests.fields.notes'))
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Who asked is an account, not four boxes to retype. Their address and
     * telephone are shown beside the picker rather than edited here: they
     * belong to the user record, and a correction has to reach every order that
     * reader has open.
     */
    private static function readerSection(): Section
    {
        return Section::make(__('book_requests.sections.reader'))
            ->schema([
                Select::make('user_id')
                    ->label(__('book_requests.fields.user_id'))
                    ->helperText(__('book_requests.hints.user_id'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextEntry::make('user.email')
                    ->label(__('user.fields.email'))
                    ->copyable()
                    ->visibleOn('edit'),

                TextEntry::make('user.phone')
                    ->label(__('user.fields.phone'))
                    ->placeholder('—')
                    ->visibleOn('edit'),
            ])
            ->columns(1);
    }

    private static function handlingSection(): Section
    {
        return Section::make(__('book_requests.sections.handling'))
            ->schema([
                Select::make('status')
                    ->label(__('book_requests.fields.status'))
                    ->options(BookRequestStatus::class)
                    ->default(BookRequestStatus::Pendiente)
                    ->selectablePlaceholder(false)
                    ->required(),

                Textarea::make('admin_notes')
                    ->label(__('book_requests.fields.admin_notes'))
                    ->helperText(__('book_requests.hints.admin_notes'))
                    ->rows(5),
            ])
            ->columns(1);
    }
}
