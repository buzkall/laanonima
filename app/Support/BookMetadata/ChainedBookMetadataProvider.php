<?php

namespace App\Support\BookMetadata;

use App\Support\WorkTrail;

/**
 * Consults each provider in turn and merges what they return, so that a source
 * with a good synopsis and one with a good cover together produce a complete
 * record. Earlier providers win on any field both supply.
 *
 * A line per provider, before it is called: this is the loop that spends the
 * time, and it is the only place that can say which of three hosts was the one
 * that never answered. Providers swallow their own failures by contract -- a
 * source that is down reads as a miss -- so without the trail a lookup that
 * cost fifteen seconds and a lookup that cost fifteen milliseconds leave
 * exactly the same trace, which is none.
 */
class ChainedBookMetadataProvider implements BookMetadataProvider
{
    /**
     * @param  array<int, BookMetadataProvider>  $providers
     */
    public function __construct(private array $providers) {}

    public function find(string $isbn13): ?BookMetadata
    {
        $result = null;

        $trail = WorkTrail::start('metadata', ['isbn13' => $isbn13]);

        foreach ($this->providers as $provider) {
            $trail->step('asking ' . class_basename($provider));

            $metadata = $provider->find($isbn13);

            $trail->step(class_basename($provider) . ' answered', [
                'found' => $metadata instanceof BookMetadata,
                'cover' => filled($metadata?->coverSourceUrl),
            ]);

            if ($metadata === null) {
                continue;
            }

            $result = $result?->merge($metadata) ?? $metadata;
        }

        $trail->finish(['found' => $result instanceof BookMetadata]);

        return $result;
    }
}
