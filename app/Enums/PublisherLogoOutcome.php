<?php

namespace App\Enums;

/**
 * What came of looking for a publisher's logotype.
 */
enum PublisherLogoOutcome
{
    /** A logotype was downloaded and filed. */
    case Attached;

    /** The publisher already had one and nobody asked to replace it; nothing was asked. */
    case Kept;

    /** Neither Wikidata nor the publisher's website had one that could be used. */
    case NotFound;
}
