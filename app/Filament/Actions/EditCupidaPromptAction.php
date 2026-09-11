<?php

namespace App\Filament\Actions;

use App\Ai\Agents\CupidaAgent;
use App\Settings\CupidaSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * What the shop tells La Cupida, over what it is told in code.
 *
 * The modal shows both halves because only seeing one of them is how you end up
 * writing an instruction that is already there, or one that contradicts it. The
 * top half is the baseline, read-only: it is what keeps a recommendation
 * honest -- choose from what you are given, invent nothing, never claim to be a
 * person -- and it changes with a deploy, not with a text box. The bottom half
 * is the shop's, and is appended to it.
 *
 * The text the deck shows over the first card lives in the same modal. It is
 * the other thing the shop says to a reader in its own words, and a
 * bookseller looking for one will look where the other is.
 *
 * Deliberately not a settings resource. There is one row and two fields; a
 * resource for it would be three files and a navigation entry for a modal.
 */
class EditCupidaPromptAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editCupidaPrompt';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('cupida.admin.prompt.action'))
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading(__('cupida.admin.prompt.heading'))
            ->modalDescription(__('cupida.admin.prompt.description'))
            ->modalSubmitActionLabel(__('cupida.admin.prompt.save'))
            ->modalWidth('2xl')
            ->fillForm(fn(): array => [
                'extra_instructions' => app(CupidaSettings::class)->extra_instructions,
                'coach_text'         => app(CupidaSettings::class)->coach_text,
            ])
            ->schema([
                Section::make(__('cupida.admin.prompt.base_heading'))
                    ->description(__('cupida.admin.prompt.base_hint'))
                    ->collapsed()
                    ->schema([
                        /* The baseline is a list, and a TextEntry renders text
                           as text -- so without this it arrives as one gray
                           paragraph and nobody reads it. Escaped first: it is
                           only ever the string in CupidaAgent, but html() is
                           html(). */
                        TextEntry::make('base')
                            ->hiddenLabel()
                            ->html()
                            ->state(fn(): string => nl2br(e(new CupidaAgent([])->baseInstructions()))),
                    ]),

                Textarea::make('extra_instructions')
                    ->label(__('cupida.admin.prompt.extra'))
                    ->helperText(__('cupida.admin.prompt.extra_hint'))
                    ->rows(8)
                    ->maxLength(2000)
                    ->autosize(),

                Textarea::make('coach_text')
                    ->label(__('cupida.admin.prompt.coach'))
                    ->helperText(__('cupida.admin.prompt.coach_hint'))
                    ->rows(4)
                    ->maxLength(500)
                    ->autosize(),
            ])
            ->action(function(array $data): void {
                $settings = app(CupidaSettings::class);

                $settings->extra_instructions = $data['extra_instructions'] ?? null;
                $settings->coach_text = $data['coach_text'] ?? null;
                $settings->save();

                Notification::make()
                    ->success()
                    ->title(__('cupida.admin.prompt.saved'))
                    ->send();
            });
    }
}
