<?php

namespace App\Filament\Resources\Publishers\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PublisherForm
{
    /**
     * The catalogue data on the left, the logotype beside it.
     *
     * A publisher is a short record, so everything fits on one screen: the
     * name and web presence in the wide column, the logo where the bookseller
     * can see at a glance whether one has been uploaded at all.
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
                                self::presentationSection(),
                            ])
                            ->columnSpan(2),
                        Grid::make(1)
                            ->schema([
                                self::logoSection(),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function identificationSection(): Section
    {
        return Section::make(__('publishers.sections.identification'))
            ->schema([
                TextInput::make('name')
                    ->label(__('publishers.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label(__('publishers.fields.slug'))
                    ->helperText(__('publishers.hints.slug'))
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('website')
                    ->label(__('publishers.fields.website'))
                    ->url()
                    ->prefixIcon('heroicon-m-globe-alt')
                    ->maxLength(255),
            ])
            ->columns(2);
    }

    private static function presentationSection(): Section
    {
        return Section::make(__('publishers.sections.presentation'))
            ->schema([
                Textarea::make('description')
                    ->label(__('publishers.fields.description'))
                    ->rows(6)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    private static function logoSection(): Section
    {
        return Section::make(__('publishers.sections.logo'))
            ->schema([
                FileUpload::make('logo_path')
                    ->label(__('publishers.fields.logo_path'))
                    ->image()
                    ->disk(config('books.logos.disk'))
                    ->directory(config('books.logos.directory'))
                    ->visibility('public')
                    ->imageEditor()
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }
}
