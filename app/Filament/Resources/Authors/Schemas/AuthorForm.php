<?php

namespace App\Filament\Resources\Authors\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthorForm
{
    /**
     * An author is a short record, so it is one full-width block: name and
     * slug side by side, the biography beneath.
     *
     * The same two fields the book form's "new author" modal asks for, plus
     * the slug, so a person created in passing from a book can be filled in
     * properly here later.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
