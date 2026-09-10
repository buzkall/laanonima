<?php

use App\Filament\Pages\CorreosPlayground;
use App\Models\User;
use App\Support\Correos\FakeCorreos;
use App\Support\Correos\FakePdf;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function(): void {
    $this->actingAs(User::factory()->admin()->create());

    config()->set('correos.contract_number', '12345678');
    config()->set('correos.client_number', '1234567890');
    config()->set('correos.labeller_code', '0001');
    config()->set('correos.sender', [
        'name'     => 'La Anónima',
        'address'  => 'Calle Remitente 1',
        'locality' => 'A Coruña',
        'province' => '15',
        'cp'       => '15001',
        'country'  => 'ESP',
    ]);

    config()->set('correos.fake', true);
    FakeCorreos::install($this->app);

    /* Nothing may reach the network, not even the OAuth token: Http::fake with
       no arguments turns any real call into an empty 200, and preventStray-
       Requests turns it into a failure instead. */
    Http::preventStrayRequests();
});

/** The base64 payload Livewire captured for the last triggered download. */
function downloadedPdf(Testable $component): string
{
    return base64_decode(data_get($component->effects, 'download.content'));
}

it('answers a validation without leaving the machine', function(): void {
    Livewire::test(CorreosPlayground::class)
        ->callAction('validateShipment')
        ->assertSee('"validationErrorCount": 0')
        ->assertNotified(__('correos.notifications.ok', ['call' => __('correos.actions.validate')]));
});

it('answers a create with codes derived from the reference it was sent', function(): void {
    Livewire::test(CorreosPlayground::class)
        ->fillForm(['clientReference' => 'PEDIDO-4321'])
        ->callAction('createShipment')
        ->assertSchemaStateSet([
            'shipmentCode' => 'PQFAKE0000004321',
            'packageCode'  => 'PQ1FAKE0000004321',
        ]);
});

it('tracks the package code it is given', function(): void {
    Livewire::test(CorreosPlayground::class)
        ->fillForm(['packageCode' => 'PQ1FAKE0000004321'])
        ->callAction('track')
        ->assertSee('PQ1FAKE0000004321')
        ->assertSee('Envío prerregistrado');
});

it('hands over a PDF a reader can open', function(): void {
    $component = Livewire::test(CorreosPlayground::class)
        ->fillForm(['shipmentCode' => 'PQFAKE0000004321'])
        ->callAction('printLabel')
        ->assertFileDownloaded('etiqueta-PQFAKE0000004321.pdf', contentType: 'application/pdf');

    expect(downloadedPdf($component))->toStartWith('%PDF-')->toContain('PQFAKE0000004321');
});

it('says on the page that the responses are made up', function(): void {
    Livewire::test(CorreosPlayground::class)
        ->assertSee(__('correos.fake.heading'))
        ->assertDontSee(__('correos.credentials.heading'));
});

it('answers every read the page can make', function(string $action, array $state): void {
    Livewire::test(CorreosPlayground::class)
        ->fillForm($state)
        ->callAction($action)
        ->assertOk()
        ->assertNotified();
})->with([
    'consulta'   => ['queryShipments', ['shipmentCode' => 'PQFAKE0000004321']],
    'referencia' => ['packagesByReference', ['clientReference' => 'PEDIDO-4321']],
    'expedición' => ['expedition', ['expeditionCode' => 'EXP001234567890']],
    'bultos'     => ['expeditionPackages', ['expeditionCode' => 'EXP001234567890']],
    'documentos' => ['documentBackoffice', ['shipmentCode' => 'PQFAKE0000004321']],
    'backoffice' => ['backofficeShipment', ['shipmentCode' => 'PQFAKE0000004321']],
    'errores'    => ['backofficeErrors', []],
    'pendientes' => ['backofficeWaiting', []],
    'totales'    => ['backofficeTotal', []],
]);

it('builds a PDF whose xref points at its objects', function(): void {
    $pdf = FakePdf::make(['Una línea', 'Otra (con paréntesis)']);

    expect($pdf)->toStartWith('%PDF-1.4')->toEndWith("%%EOF\n");

    preg_match_all('/^(\d{10}) 00000 n $/m', $pdf, $matches);

    // Five objects, and every offset lands on the "N 0 obj" it claims.
    expect($matches[1])->toHaveCount(5);

    foreach ($matches[1] as $index => $offset) {
        expect(substr($pdf, (int)$offset, strlen((string)($index + 1)) + 6))
            ->toBe(($index + 1) . ' 0 obj');
    }

    // startxref points at the table itself.
    preg_match('/startxref\n(\d+)/', $pdf, $start);

    expect(substr($pdf, (int)$start[1], 4))->toBe('xref');
});
