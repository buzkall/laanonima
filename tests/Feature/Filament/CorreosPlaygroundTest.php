<?php

use App\Filament\Pages\CorreosPlayground;
use App\Models\User;
use Arzcode\LaravelCorreos\Correos;
use Arzcode\LaravelCorreos\Data\Labels\LabelsResponseData;
use Arzcode\LaravelCorreos\Data\Preregister\DeliveryResponseData;
use Arzcode\LaravelCorreos\Exceptions\CorreosApiException;
use Arzcode\LaravelCorreos\Resources\LabelsResource;
use Arzcode\LaravelCorreos\Resources\PreregisterResource;
use Arzcode\LaravelCorreos\Resources\TrackingResource;
use Filament\Notifications\Notification;
use Livewire\Livewire;
use Mockery\CompositeExpectation;
use Saloon\Http\Response;

beforeEach(function(): void {
    $this->actingAs(User::factory()->admin()->create());

    // The sender is empty by default -- the shop's address is not in the
    // repository -- and an incomplete sender is a payload the page refuses to
    // send at all, which would be the only thing these tests ever exercised.
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
});

/**
 * Swap the SDK in the container for a mock of one of its three resources. The
 * page resolves Correos on every call, so nothing else has to be
 * touched.
 */
function fakeCorreos(string $resource, string $method): CompositeExpectation
{
    $mock = Mockery::mock([
        'preregister' => PreregisterResource::class,
        'labels'      => LabelsResource::class,
        'tracking'    => TrackingResource::class,
    ][$resource]);

    $correos = Mockery::mock(Correos::class);
    $correos->shouldReceive($resource)->andReturn($mock);

    app()->instance(Correos::class, $correos);

    return $mock->shouldReceive($method)->once();
}

/** A Saloon response with just the two things the failure path reads off it. */
function fakeFailedResponse(int $status, string $body): Response
{
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn($status);
    $response->shouldReceive('body')->andReturn($body);

    return $response;
}

function deliveryResponse(): DeliveryResponseData
{
    return DeliveryResponseData::from([
        'fileIdentifier' => 'FILE001',
        'result'         => 0,
        'shipments'      => [[
            'validationErrorCount' => 0,
            'shipmentCode'         => 'PQXYZ1234567890',
            'entryDate'            => '09/09/2026',
            'packages'             => [['packageId' => '1', 'packageCode' => 'PQ1DR4A0000012345678']],
            'error'                => null,
        ]],
    ]);
}

it('renders the playground', function(): void {
    Livewire::test(CorreosPlayground::class)->assertOk();
});

it('stays out of production', function(): void {
    expect(CorreosPlayground::canAccess())->toBeTrue();

    app()->detectEnvironment(fn(): string => 'production');

    expect(CorreosPlayground::canAccess())->toBeFalse();
});

it('prefills the contract from config', function(): void {
    Livewire::test(CorreosPlayground::class)
        ->assertSchemaStateSet([
            'contractNumber' => '12345678',
            'clientNumber'   => '1234567890',
            'labellerCode'   => '0001',
        ]);
});

it('warns about missing credentials', function(): void {
    /* Offline mode answers for the credentials it does not have, so its own
       panel takes this one's place. Said out loud rather than inherited: with
       CORREOS_FAKE set in a developer's .env this test read the flag off their
       machine and failed there and nowhere else. */
    config()->set('correos.fake', false);
    config()->set('laravel-correos.oauth.client_id', '');

    Livewire::test(CorreosPlayground::class)->assertSee(__('correos.credentials.heading'));
});

it('validates a shipment and renders the response', function(): void {
    fakeCorreos('preregister', 'validateShipments')->andReturn(deliveryResponse());

    Livewire::test(CorreosPlayground::class)
        ->callAction('validateShipment')
        ->assertSee('PQXYZ1234567890')
        ->assertNotified(Notification::make()->success()->title(__('correos.notifications.ok', [
            'call' => __('correos.actions.validate'),
        ])));
});

it('carries the codes of a created shipment into the label and tracking fields', function(): void {
    fakeCorreos('preregister', 'createShipments')->andReturn(deliveryResponse());

    Livewire::test(CorreosPlayground::class)
        ->callAction('createShipment')
        ->assertSchemaStateSet([
            'shipmentCode' => 'PQXYZ1234567890',
            'packageCode'  => 'PQ1DR4A0000012345678',
        ]);
});

it('shows the raw body when Correos answers with an error', function(): void {
    fakeCorreos('preregister', 'validateShipments')->andThrow(new CorreosApiException(
        response: fakeFailedResponse(401, '{"code":"401","message":"Unauthorized"}'),
        message: 'Unauthorized',
        code: 401,
        errorCode: '401',
        moreInformation: 'Credenciales no válidas',
    ));

    Livewire::test(CorreosPlayground::class)
        ->callAction('validateShipment')
        ->assertSee('Unauthorized')
        ->assertNotified(Notification::make()->danger()->title(__('correos.notifications.failed_with_status', [
            'call'   => __('correos.actions.validate'),
            'status' => 401,
        ]))->body('Credenciales no válidas')->persistent());
});

it('reports a connection failure instead of blowing up the page', function(): void {
    fakeCorreos('tracking', 'searchShipment')->andThrow(new RuntimeException('cURL error 28: Operation timed out'));

    Livewire::test(CorreosPlayground::class)
        ->fillForm(['packageCode' => 'PQ1DR4A0000012345678'])
        ->callAction('track')
        ->assertOk()
        ->assertSee('Operation timed out');
});

it('stops a call whose payload is incomplete before it leaves', function(): void {
    // No resource mock: reaching the SDK at all would be the failure here.
    app()->instance(Correos::class, Mockery::mock(Correos::class));

    Livewire::test(CorreosPlayground::class)
        ->fillForm(['packageCode' => ''])
        ->callAction('track')
        ->assertNotified(__('correos.notifications.missing_fields'));
});

it('downloads the label PDF', function(): void {
    fakeCorreos('labels', 'printLabels')->andReturn(LabelsResponseData::from([
        'pdf'   => base64_encode('%PDF-1.4 fake'),
        'zpl'   => null,
        'xml'   => null,
        'error' => null,
    ]));

    Livewire::test(CorreosPlayground::class)
        ->fillForm(['shipmentCode' => 'PQXYZ1234567890'])
        ->callAction('printLabel')
        ->assertFileDownloaded('etiqueta-PQXYZ1234567890.pdf', contentType: 'application/pdf');
});

it('warns when a successful call carries no PDF', function(): void {
    fakeCorreos('labels', 'printLabels')->andReturn(LabelsResponseData::from([
        'pdf'   => null,
        'zpl'   => null,
        'xml'   => null,
        'error' => null,
    ]));

    Livewire::test(CorreosPlayground::class)
        ->fillForm(['shipmentCode' => 'PQXYZ1234567890'])
        ->callAction('printLabel')
        ->assertNoFileDownloaded()
        ->assertNotified(__('correos.notifications.no_pdf'));
});
