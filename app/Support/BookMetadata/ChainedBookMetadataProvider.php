<?php

namespace App\Support\BookMetadata;

/**
 * Consults each provider in turn and merges what they return, so that a source
 * with a good synopsis and one with a good cover together produce a complete
 * record. Earlier providers win on any field both supply.
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

        foreach ($this->providers as $provider) {
            $metadata = $provider->find($isbn13);

            if ($metadata === null) {
                continue;
            }

            $result = $result?->merge($metadata) ?? $metadata;
        }

        return $result;
    }
}
