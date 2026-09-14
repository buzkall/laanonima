<?php

namespace App\Filament\Resources\Publishers\Actions;

use App\Actions\Publishers\MergePublishers;
use App\Models\Publisher;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Fold other publishers into this row.
 *
 * The row the button is on is the one that survives -- it is the name already
 * on screen, and picking it by clicking it beats picking it out of a second
 * select -- so the modal asks the one question left: which duplicates go.
 *
 * Authorized on its own `merge` ability rather than on `delete`: the absorbed
 * rows are deleted, but only after everything they held has moved across, so
 * the demo's catch on `delete` (see `DemoMode`) leaves the button in place.
 */
class MergePublishersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'merge';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('publishers.merge.label'))
            ->icon(Heroicon::OutlinedArrowsPointingIn)
            ->authorize('merge')
            ->modalHeading(fn(Publisher $record): string => __('publishers.merge.heading', ['name' => $record->name]))
            ->modalDescription(fn(Publisher $record): string => __('publishers.merge.description', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('publishers.merge.submit'))
            ->schema(fn(Publisher $record): array => [
                Select::make('absorbed')
                    ->label(__('publishers.merge.absorbed'))
                    ->helperText(__('publishers.merge.absorbed_hint'))
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->options($this->candidates($record)),
            ])
            ->action($this->merge(...));
    }

    /**
     * Every other publisher, named with the size of the shelf that would move.
     *
     * The whole list rather than a search callback: six hundred rows is a
     * couple of columns of one query, and a bookseller hunting a duplicate is
     * usually looking at a name they cannot quite remember the spelling of.
     *
     * @return array<int, string>
     */
    private function candidates(Publisher $survivor): array
    {
        return Publisher::query()
            ->whereKeyNot($survivor->getKey())
            ->withCount('books')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(Publisher $publisher): array => [
                $publisher->getKey() => trans_choice('publishers.merge.option', $publisher->books_count, [
                    'name'  => $publisher->name,
                    'count' => $publisher->books_count,
                ]),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function merge(Publisher $record, array $data, MergePublishers $mergePublishers): void
    {
        $absorbed = Publisher::query()->whereKey($data['absorbed'])->get();

        $moved = $mergePublishers($record, $absorbed);

        Notification::make()
            ->success()
            ->title(__('publishers.merge.done'))
            ->body(trans_choice('publishers.merge.moved', $moved, [
                'name'  => $record->name,
                'count' => $moved,
            ]))
            ->send();
    }
}
