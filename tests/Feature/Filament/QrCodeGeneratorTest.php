<?php

use App\Filament\Pages\QrCodeGenerator;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function(): void {
    $this->admin = User::factory()->admin()->create();

    $this->actingAs($this->admin);
});

/** The base64 payload Livewire captured for the last triggered download. */
function downloadedContent(Testable $component): string
{
    return base64_decode(data_get($component->effects, 'download.content'));
}

it('renders the generator', function(): void {
    Livewire::test(QrCodeGenerator::class)->assertOk();
});

it('prefills the form with the application URL', function(): void {
    Livewire::test(QrCodeGenerator::class)
        ->assertSchemaStateSet(['url' => config('app.url')]);
});

it('validates the destination', function(?string $url, string $rule): void {
    Livewire::test(QrCodeGenerator::class)
        ->fillForm(['url' => $url])
        ->callAction('downloadSvg')
        ->assertHasFormErrors(['url' => $rule])
        ->assertNoFileDownloaded();
})->with([
    'vacía'           => [null, 'required'],
    'no es URL'       => ['no-soy-una-url', 'url'],
    'demasiado larga' => ['https://laanonimalibreria.com/' . str_repeat('a', 500), 'max'],
]);

it('previews a QR once the destination is valid', function(): void {
    // <polygon> is the tilde of the isotipo. Filament's own header icons are
    // all <path>, so it only appears when a preview was actually rendered.
    Livewire::test(QrCodeGenerator::class)
        ->fillForm(['url' => 'https://laanonimalibreria.com'])
        ->assertSee('<polygon', escape: false);
});

it('shows a placeholder instead of a preview while the URL is incomplete', function(): void {
    Livewire::test(QrCodeGenerator::class)
        ->fillForm(['url' => 'https:/'])
        ->assertSee(__('qr.placeholders.preview'))
        ->assertDontSee('<polygon', escape: false);
});

it('downloads the vector version named after the destination', function(): void {
    $component = Livewire::test(QrCodeGenerator::class)
        ->fillForm(['url' => 'https://laanonimalibreria.com'])
        ->callAction('downloadSvg')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded('qr-laanonimalibreria-com.svg', contentType: 'image/svg+xml');

    expect(downloadedContent($component))->toStartWith('<svg');
});

it('downloads a thermal PNG at the default printer width', function(): void {
    $component = Livewire::test(QrCodeGenerator::class)
        ->fillForm(['url' => 'https://laanonimalibreria.com'])
        ->callAction('downloadThermalPng')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded('qr-laanonimalibreria-com.png', contentType: 'image/png');

    $info = getimagesizefromstring(downloadedContent($component));

    expect($info['mime'])->toBe('image/png')
        ->and($info[0])->toBe(config('qr.thermal.default'));
});

it('names each download after its own landing page', function(): void {
    Livewire::test(QrCodeGenerator::class)
        ->fillForm(['url' => 'https://laanonimalibreria.com/editorial-del-mes/anagrama?utm_source=ticket'])
        ->callAction('downloadThermalPng')
        ->assertFileDownloaded('qr-laanonimalibreria-com-editorial-del-mes-anagrama.png');
});
