<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Test Impact Analysis
|--------------------------------------------------------------------------
|
| TIA diffs the working tree against git to decide which tests to execute. It
| resolves the default branch from "origin/HEAD", which is unavailable while the
| repository has no remote, so pin it explicitly.
|
| No "watch" patterns are needed here: pcov already records config/, routes/ and
| bootstrap/ as real edges because they execute on every boot, and Pest's Laravel
| defaults cover the files coverage cannot see (views, lang, migrations). Adding
| globs for those would only make selection less precise.
|
*/

pest()->tia()->defaultBranch('main');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn() => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Decode a committed API fixture, captured from the real endpoint.
 *
 * Pest's own fixture() resolves the path; this decodes it.
 *
 * @return array<string, mixed>
 */
function apiFixture(string $file): array
{
    return json_decode(
        (string)file_get_contents(fixture("{$file}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * A real JPEG of the given size, for faking a cover download.
 *
 * The covers pipeline decodes and measures what it receives, so a one-pixel
 * stub will not do: tests have to hand it a genuine image.
 */
function fakeCover(int $width = 800, int $height = 1200): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));

    ob_start();
    imagejpeg($image);

    return (string)ob_get_clean();
}

/**
 * GD's resampler does not land on the source colour to the byte, and JPEG costs
 * another point or two, so a colour read off a cover is compared channel by
 * channel with a tolerance rather than as a hex string.
 */
function expectColorNear(?string $color, string $expected): void
{
    expect($color)->toMatch('/^#[0-9a-f]{6}$/');

    foreach ([1, 3, 5] as $offset) {
        $channel = hexdec(substr((string)$color, $offset, 2));

        expect($channel)->toBeGreaterThanOrEqual(hexdec(substr($expected, $offset, 2)) - 8)
            ->and($channel)->toBeLessThanOrEqual(hexdec(substr($expected, $offset, 2)) + 8);
    }
}

/**
 * Where the partial translation overrides live.
 *
 * Spelled out rather than read from `lang_path()`, because a Pest dataset is
 * resolved before the application is booted and the helper is not there yet.
 */
function langVendorPath(): string
{
    return dirname(__DIR__) . '/lang/vendor';
}

/**
 * Every partial translation override committed under lang/vendor.
 *
 * Nested groups mean the tree is arbitrarily deep -- `auth/pages/login.php`
 * lives three levels under the locale -- so it is walked rather than globbed.
 *
 * @return list<string>
 */
function translationOverrideFiles(): array
{
    $files = [];

    $tree = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(langVendorPath(), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($tree as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Split a lang/vendor override path into the package it overrides and the
 * group inside it: `lang/vendor/filament-panels/es/auth/pages/login.php` is
 * `filament-panels` and `auth/pages/login`.
 *
 * @return array{0: string, 1: string}
 */
function translationOverrideTarget(string $file): array
{
    $relative = str($file)
        ->after(langVendorPath() . '/')
        ->before('.php');

    return [
        (string)$relative->before('/'),
        (string)$relative->after('/es/'),
    ];
}
