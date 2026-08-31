<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Panel;

final class PanelUrl
{
    /**
     * Whether a URL points inside a panel.
     *
     * Compared on the path so an absolute URL, a relative one and a URL carrying a
     * query string all answer the same, and on a segment boundary so a panel at
     * `/client` does not claim `/clients`.
     */
    public static function belongsTo(?string $url, Panel $panel): bool
    {
        if (blank($url)) {
            return false;
        }

        $path = '/' . trim((string)parse_url($url, PHP_URL_PATH), '/');
        $prefix = '/' . trim($panel->getPath(), '/');

        return $prefix === '/'
            || $path === $prefix
            || str_starts_with($path, $prefix . '/');
    }

    /**
     * Whether a URL is a page of the shop itself rather than of any panel.
     *
     * A panel URL is only safe for the role that owns it, but the shop is open
     * to everyone, so a public address remembered before signing in can always
     * be followed afterwards -- which is how a reader turned away from the
     * request form lands back on it.
     */
    public static function isPublic(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        foreach (Filament::getPanels() as $panel) {
            if (self::belongsTo($url, $panel)) {
                return false;
            }
        }

        return true;
    }
}
