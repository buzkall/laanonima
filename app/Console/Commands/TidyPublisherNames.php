<?php

namespace App\Console\Commands;

use App\Models\Publisher;
use App\Support\PublisherName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recase the publishers filed before the names were tidied on the way in.
 *
 * `Publisher::named()` only reaches a row the day another book arrives from the
 * same imprint, and eighty-odd of them were already shouting by then. This is
 * that one pass, run by hand.
 *
 * It renames and nothing else: `PublisherName` never changes what a name slugs
 * to, so no row moves, no book changes publisher and no public URL breaks. The
 * duplicates that are more than capitals are somebody's judgment and belong in
 * the merge action on the listing, not in a command.
 */
#[Signature('publishers:tidy {--pretend : List what would be renamed and write nothing}')]
#[Description('Rewrite publisher names the catalog filed in capitals')]
class TidyPublisherNames extends Command
{
    public function handle(): int
    {
        /** @var array<int, array{0: string, 1: string}> $renamed */
        $renamed = [];

        Publisher::query()->orderBy('name')->each(function(Publisher $publisher) use (&$renamed): void {
            $tidied = PublisherName::normalize($publisher->name);

            if ($tidied === $publisher->name) {
                return;
            }

            $renamed[] = [$publisher->name, $tidied];

            if (! $this->option('pretend')) {
                $publisher->update(['name' => $tidied]);
            }
        });

        if ($renamed === []) {
            $this->components->info('Every publisher is already printed the way it should be.');

            return self::SUCCESS;
        }

        $this->table(['Filed as', 'Printed as'], $renamed);
        $this->components->info(sprintf(
            '%d %s %s.',
            count($renamed),
            count($renamed) === 1 ? 'publisher' : 'publishers',
            $this->option('pretend') ? 'would be renamed' : 'renamed',
        ));

        return self::SUCCESS;
    }
}
