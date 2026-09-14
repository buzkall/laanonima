<?php

namespace App\Actions\Publishers;

use App\Enums\LogoOrigin;
use App\Enums\PublisherLogoOutcome;
use App\Models\Publisher;
use App\Support\PublisherLogos\PublisherLogoResult;
use App\Support\PublisherLogos\WebsiteIconSource;
use App\Support\PublisherLogos\WikidataPublisher;
use App\Support\PublisherLogos\WikidataPublisherSource;

/**
 * Find a publisher's logotype and file it: Wikidata first, its website second.
 *
 * Wikidata goes first because what it holds is a logotype somebody chose; a
 * website only declares icons, and most of those are squares. The lookup also
 * fills in a website the publisher did not have -- which is often what makes
 * the second step possible -- and never overwrites one somebody typed.
 *
 * A logotype already on the publisher is never touched unless `$replace`, and
 * even then it is swapped only once a new one has actually been downloaded: a
 * retry that finds nothing keeps what was there.
 *
 * `logo_checked_at` is stamped whether or not anything was found. It records
 * that we asked, so the next run does not ask about the same misses again.
 */
class FetchPublisherLogo
{
    public function __construct(
        private readonly WikidataPublisherSource $wikidata,
        private readonly WebsiteIconSource $websiteIcons,
        private readonly DownloadPublisherLogo $download,
    ) {}

    /**
     * @param  string|null  $qid  a Wikidata item named by hand, which skips the search
     */
    public function __invoke(Publisher $publisher, bool $replace = false, ?string $qid = null, bool $useWebsite = true): PublisherLogoResult
    {
        /* A relation query rather than hasMedia(): the loaded media can be stale. */
        if (! $replace && $publisher->media()->where('collection_name', Publisher::LOGO_COLLECTION)->exists()) {
            return new PublisherLogoResult(PublisherLogoOutcome::Kept);
        }

        $match = $this->wikidata->find($publisher->name, $qid);
        $websiteFilled = $this->fillWebsite($publisher, $match);

        $origin = $this->fromWikidata($publisher, $match)
            ?? ($useWebsite ? $this->fromWebsite($publisher) : null);

        $publisher->forceFill(['logo_checked_at' => now()])->saveQuietly();

        return new PublisherLogoResult(
            outcome: $origin instanceof LogoOrigin ? PublisherLogoOutcome::Attached : PublisherLogoOutcome::NotFound,
            origin: $origin,
            websiteFilled: $websiteFilled,
        );
    }

    private function fillWebsite(Publisher $publisher, ?WikidataPublisher $match): bool
    {
        if (blank($match?->website) || filled($publisher->website)) {
            return false;
        }

        $publisher->update(['website' => $match->website]);

        return true;
    }

    private function fromWikidata(Publisher $publisher, ?WikidataPublisher $match): ?LogoOrigin
    {
        if (! $match instanceof WikidataPublisher || ! $match->hasLogo()) {
            return null;
        }

        $bytes = ($this->download)($match->imageUrl, LogoOrigin::Wikidata, $publisher->slug);

        if ($bytes === null) {
            return null;
        }

        $this->attach($publisher, $bytes, LogoOrigin::Wikidata, [
            'qid'         => $match->qid,
            'file'        => $match->file,
            'artist'      => $match->artist,
            'license'     => $match->license,
            'license_url' => $match->licenseUrl,
            'source_url'  => $match->sourceUrl,
        ]);

        return LogoOrigin::Wikidata;
    }

    private function fromWebsite(Publisher $publisher): ?LogoOrigin
    {
        if (! config('publishers.logos.website.enabled') || blank($publisher->website)) {
            return null;
        }

        foreach ($this->websiteIcons->candidates($publisher->website) as $url) {
            $bytes = ($this->download)($url, LogoOrigin::Website, $publisher->slug);

            if ($bytes !== null) {
                $this->attach($publisher, $bytes, LogoOrigin::Website, [
                    'source_url' => $url,
                    'website'    => $publisher->website,
                ]);

                return LogoOrigin::Website;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $credit
     */
    private function attach(Publisher $publisher, string $bytes, LogoOrigin $origin, array $credit): void
    {
        $publisher->addMediaFromString($bytes)
            ->usingFileName("{$publisher->slug}-logo." . DownloadPublisherLogo::EXTENSION)
            ->withCustomProperties([
                'source' => $origin->value,
                'credit' => array_filter($credit, filled(...)),
            ])
            ->toMediaCollection(Publisher::LOGO_COLLECTION);
    }
}
