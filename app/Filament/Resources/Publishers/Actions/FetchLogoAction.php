<?php

namespace App\Filament\Resources\Publishers\Actions;

use App\Actions\Publishers\FetchPublisherLogo;
use App\Enums\LogoOrigin;
use App\Enums\PublisherLogoOutcome;
use App\Models\Publisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Look this publisher's logotype up on Wikidata and its website, now.
 *
 * The one-publisher version of `publishers:logos`, and an explicit retry: it
 * always replaces a logotype already there -- but only once a new one has been
 * downloaded, and only after asking when there is one to lose.
 *
 * The modal is hidden rather than the confirmation switched off: Filament opens
 * a modal for any action with a heading or a description of its own, so a
 * conditional `requiresConfirmation()` alone asked every publisher -- including
 * the ones with no logotype -- whether to replace the one they did not have.
 * `logo_checked_at` plays no part here; it only decides what the command asks.
 *
 * Authorized on `update`, which the demo does not refuse (see DemoMode): it
 * files an image and may fill a website, and deletes nothing.
 */
class FetchLogoAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'fetchLogo';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('publishers.logo_fetch.label'))
            ->icon(Heroicon::OutlinedPhoto)
            ->authorize('update')
            ->requiresConfirmation()
            ->modalHidden(fn(Publisher $record): bool => ! $record->hasMedia(Publisher::LOGO_COLLECTION))
            ->modalHeading(fn(Publisher $record): string => __('publishers.logo_fetch.heading', ['name' => $record->name]))
            ->modalDescription(__('publishers.logo_fetch.replace_description'))
            ->modalSubmitActionLabel(__('publishers.logo_fetch.submit'))
            ->action($this->fetch(...));
    }

    private function fetch(Publisher $record, FetchPublisherLogo $fetchPublisherLogo): void
    {
        /* Up to half a dozen requests with timeouts of several seconds each,
           inside a web request. */
        set_time_limit(60);

        $result = $fetchPublisherLogo($record, replace: true);

        $website = $result->websiteFilled ? ' ' . __('publishers.logo_fetch.website_filled') : '';

        if ($result->outcome === PublisherLogoOutcome::Attached) {
            Notification::make()
                ->success()
                ->title(__('publishers.logo_fetch.done_title'))
                ->body(__($result->origin === LogoOrigin::Wikidata ? 'publishers.logo_fetch.done_wikidata' : 'publishers.logo_fetch.done_website') . $website)
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title(__('publishers.logo_fetch.missing_title'))
            ->body(__('publishers.logo_fetch.missing_body') . $website)
            ->send();
    }
}
