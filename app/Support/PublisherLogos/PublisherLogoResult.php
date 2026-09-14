<?php

namespace App\Support\PublisherLogos;

use App\Enums\LogoOrigin;
use App\Enums\PublisherLogoOutcome;

final readonly class PublisherLogoResult
{
    public function __construct(
        public PublisherLogoOutcome $outcome,
        /** Set only when a logotype was attached. */
        public ?LogoOrigin $origin = null,
        /** The lookup filled in a website the publisher did not have. */
        public bool $websiteFilled = false,
    ) {}
}
