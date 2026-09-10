<?php

namespace App\Filament\Resources\Authors\Schemas;

use App\Models\Author;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthorForm
{
    /**
     * An author is a short record: name and slug side by side, the biography
     * beneath, and the portrait in a narrow column beside them where the
     * bookseller can see at a glance whether there is one.
     *
     * The same two fields the book form's "new author" modal asks for, plus
     * the slug, so a person created in passing from a book can be filled in
     * properly here later.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('authors.fields.name'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('slug')
                                    ->label(__('authors.fields.slug'))
                                    ->helperText(__('authors.hints.slug'))
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),

                                self::bioField()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpan(2),

                        self::portraitSection()
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function portraitSection(): Section
    {
        return Section::make(__('authors.sections.portrait'))
            ->schema([
                SpatieMediaLibraryFileUpload::make('portrait')
                    ->label(__('authors.fields.portrait'))
                    ->helperText(__('authors.hints.portrait'))
                    ->collection(Author::PORTRAIT_COLLECTION)
                    ->conversion('thumb')
                    /* Filament would otherwise upload to FILESYSTEM_DISK rather
                       than the disk the media library reads back from. */
                    ->disk(config('media-library.disk_name'))
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['3:4'])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The fields the book form's "new author" modal shares with this page.
     *
     * @return array<int, TextInput|RichEditor>
     */
    public static function quickCreateFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('authors.fields.name'))
                ->required()
                ->maxLength(255),

            self::bioField(),
        ];
    }

    /**
     * A biography is a few paragraphs with the odd emphasis or link, so the
     * toolbar stops there: no headings, tables or attachments, which the
     * public page has no styles for.
     */
    private static function bioField(): RichEditor
    {
        return RichEditor::make('bio')
            ->label(__('authors.fields.bio'))
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link'],
                ['bulletList', 'orderedList', 'blockquote'],
                ['undo', 'redo'],
            ]);
    }
}
