<?php

namespace App\Enums;

/**
 * Where a publisher's logotype was found, which decides how it is fetched.
 *
 * A Commons thumbnail is fetched from a host we chose; a website's icon from
 * whatever host the publisher's website is on, behind a different guard.
 */
enum LogoOrigin: string
{
    case Wikidata = 'wikidata';

    case Website = 'website';
}
