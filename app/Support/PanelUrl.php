<?php

namespace App\Support;

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
}
