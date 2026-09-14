<?php

namespace App\Console\Commands;

use App\Actions\Publishers\FetchPublisherLogo;
use App\Enums\LogoOrigin;
use App\Enums\PublisherLogoOutcome;
use App\Models\Publisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Find a logotype for every publisher that has none, run by hand.
 *
 * By default it only asks about publishers nobody has asked about yet: a miss is
 * stamped, and six hundred misses re-asked on every run would be thousands of
 * requests for the same answer. `--retry-misses` asks again, `--force` replaces
 * logotypes that are already there, and `--only` with `--qid` is how a
 * publisher the guards could not identify is named by hand.
 *
 * Every result wants looking at: the guards keep out other companies' logos,
 * not a publisher's own favicon standing in for its wordmark.
 */
#[Signature('publishers:logos
    {--only= : Only these publisher slugs, comma separated}
    {--qid= : Use this Wikidata item; needs exactly one --only}
    {--limit= : Look up at most this many publishers}
    {--retry-misses : Ask again about publishers an earlier run found nothing for}
    {--force : Replace the logotypes publishers already have}
    {--no-website : Only ask Wikidata, never the publisher\'s own website}
    {--delay= : Milliseconds to wait between publishers}')]
#[Description('Find a logotype for the publishers that have none, on Wikidata or their own website')]
class FetchPublisherLogos extends Command
{
    public function handle(FetchPublisherLogo $fetchPublisherLogo): int
    {
        $only = array_values(array_filter(array_map(trim(...), explode(',', (string)$this->option('only')))));
        $qid = filled($this->option('qid')) ? (string)$this->option('qid') : null;
        $force = (bool)$this->option('force');

        if ($qid !== null && count($only) !== 1) {
            $this->components->error('--qid names one Wikidata item, so it needs exactly one --only.');

            return self::FAILURE;
        }

        $publishers = Publisher::query()
            ->when($only !== [], fn(Builder $query): Builder => $query->whereIn('slug', $only))
            ->when($only === [] && ! $force, fn(Builder $query): Builder => $query->whereDoesntHave(
                'media',
                fn(Builder $media): Builder => $media->where('collection_name', Publisher::LOGO_COLLECTION),
            ))
            ->when($only === [] && ! $force && ! $this->option('retry-misses'), fn(Builder $query): Builder => $query->whereNull('logo_checked_at'))
            ->when(filled($this->option('limit')), fn(Builder $query): Builder => $query->limit((int)$this->option('limit')))
            ->orderBy('name')
            ->get();

        $tally = ['wikidata' => 0, 'website' => 0, 'missing' => 0, 'filled' => 0];

        foreach ($publishers->values() as $position => $publisher) {
            if ($position > 0) {
                Sleep::for((int)($this->option('delay') ?? config('publishers.logos.delay_ms')))->milliseconds();
            }

            try {
                $result = $fetchPublisherLogo($publisher, replace: $force, qid: $qid, useWebsite: ! $this->option('no-website'));
            } catch (Throwable $exception) {
                report($exception);
                $tally['missing']++;
                $this->components->twoColumnDetail($publisher->name, '<fg=red>failed</>');

                continue;
            }

            $verdict = match ($result->outcome) {
                PublisherLogoOutcome::Attached => '<fg=green>' . $result->origin?->value . '</>',
                PublisherLogoOutcome::Kept     => '<fg=gray>kept</>',
                PublisherLogoOutcome::NotFound => '<fg=yellow>not found</>',
            };

            match (true) {
                $result->origin === LogoOrigin::Wikidata            => $tally['wikidata']++,
                $result->origin === LogoOrigin::Website             => $tally['website']++,
                $result->outcome === PublisherLogoOutcome::NotFound => $tally['missing']++,
                default                                             => null,
            };

            if ($result->websiteFilled) {
                $tally['filled']++;
                $verdict .= ' <fg=blue>+web</>';
            }

            $this->components->twoColumnDetail($publisher->name, $verdict);
        }

        $this->components->info(sprintf(
            '%d from Wikidata, %d from websites, %d not found, %d websites filled in.',
            $tally['wikidata'],
            $tally['website'],
            $tally['missing'],
            $tally['filled'],
        ));

        return self::SUCCESS;
    }
}
