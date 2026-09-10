<?php

namespace App\Support\Correos;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use SmartDato\CorreosShipping\Auth\CorreosAuthenticator;
use SmartDato\CorreosShipping\Connectors\CorreosConnector;
use SmartDato\CorreosShipping\Connectors\LabelsConnector;
use SmartDato\CorreosShipping\Connectors\PreregisterConnector;
use SmartDato\CorreosShipping\Connectors\TrackingConnector;
use SmartDato\CorreosShipping\Requests\Labels\GetDocumentBackofficeRequest;
use SmartDato\CorreosShipping\Requests\Labels\PrintDocumentsRequest;
use SmartDato\CorreosShipping\Requests\Labels\PrintLabelsRequest;
use SmartDato\CorreosShipping\Requests\Preregister\CancelExpeditionRequest;
use SmartDato\CorreosShipping\Requests\Preregister\CancelShipmentRequest;
use SmartDato\CorreosShipping\Requests\Preregister\CreateShipmentsRequest;
use SmartDato\CorreosShipping\Requests\Preregister\GenerateShipmentCodeRequest;
use SmartDato\CorreosShipping\Requests\Preregister\GetBackofficeErrorsRequest;
use SmartDato\CorreosShipping\Requests\Preregister\GetBackofficeShipmentRequest;
use SmartDato\CorreosShipping\Requests\Preregister\GetBackofficeTotalRequest;
use SmartDato\CorreosShipping\Requests\Preregister\GetBackofficeWaitingRequest;
use SmartDato\CorreosShipping\Requests\Preregister\GetExpeditionPackagesRequest;
use SmartDato\CorreosShipping\Requests\Preregister\GetPackagesByReferenceRequest;
use SmartDato\CorreosShipping\Requests\Preregister\ModifyShipmentRequest;
use SmartDato\CorreosShipping\Requests\Preregister\QueryShipmentsRequest;
use SmartDato\CorreosShipping\Requests\Preregister\ValidateShipmentsRequest;
use SmartDato\CorreosShipping\Requests\Tracking\GetExpeditionRequest;
use SmartDato\CorreosShipping\Requests\Tracking\SearchShipmentRequest;

/**
 * Answers the Correos SDK from memory instead of over the network.
 *
 * Correos has no sandbox anyone can sign up for: pre-production credentials
 * come from a commercial contact, and the calls only work from a whitelisted
 * IPv4 on weekdays. This stands in until those arrive, so the panel's Correos
 * page can be driven end to end.
 *
 * It mocks at Saloon's sender rather than replacing the SDK, so everything
 * above the wire is the real thing: the payload is built and serialized, the
 * response is hydrated into the same DTOs, and a 200 carrying an `error` still
 * becomes a CorreosApiException. What is faked is exactly the network.
 */
class FakeCorreos
{
    /** Whatever a shipment is registered under, the label and tracking calls answer for it. */
    private const string SHIPMENT_PREFIX = 'PQFAKE';

    private const string PACKAGE_PREFIX = 'PQ1FAKE';

    public static function enabled(): bool
    {
        return (bool)config('correos.fake');
    }

    /**
     * The connectors are singletons the package binds, so they are decorated on
     * resolution rather than rebound here -- the SDK stays in charge of how
     * they are built.
     */
    public static function install(Application $app): void
    {
        $app->extend(
            CorreosAuthenticator::class,
            fn(): FakeCorreosAuthenticator => new FakeCorreosAuthenticator,
        );

        foreach ([PreregisterConnector::class, LabelsConnector::class, TrackingConnector::class] as $connector) {
            $app->extend($connector, fn(CorreosConnector $instance): CorreosConnector => $instance->withMockClient(self::client()));
        }
    }

    public static function client(): MockClient
    {
        return new MockClient([
            // Preregister
            ValidateShipmentsRequest::class => fn(PendingRequest $request): MockResponse => MockResponse::make(
                self::delivery($request, validated: true),
            ),
            CreateShipmentsRequest::class => fn(PendingRequest $request): MockResponse => MockResponse::make(
                self::delivery($request, validated: false),
            ),
            ModifyShipmentRequest::class => MockResponse::make([
                'result' => 0,
                'error'  => null,
            ]),
            GenerateShipmentCodeRequest::class => MockResponse::make([
                'result'       => 0,
                'shipmentCode' => self::SHIPMENT_PREFIX . '000000001',
                'entryDate'    => self::today(),
                'error'        => null,
            ]),
            QueryShipmentsRequest::class => fn(PendingRequest $request): MockResponse => MockResponse::make([
                'shipments' => [[
                    'shipmentCode'   => self::firstShipmentCode($request),
                    'product'        => 'PAFXB',
                    'deliveryMethod' => 'DOUAOF',
                    'contractNumber' => '00000000',
                    'clientNumber'   => '0000000000',
                    'labellerCode'   => '0001',
                    'packagesNumber' => '1',
                    'entryDate'      => self::today(),
                    'packages'       => [[
                        'packageId'   => '1',
                        'packageCode' => self::PACKAGE_PREFIX . '0000000001',
                    ]],
                ]],
                'error' => null,
            ]),
            GetPackagesByReferenceRequest::class => MockResponse::make([
                'packageCodes' => [self::PACKAGE_PREFIX . '0000000001'],
            ]),
            GetExpeditionPackagesRequest::class => MockResponse::make([
                'packageCodes' => [
                    self::PACKAGE_PREFIX . '0000000001',
                    self::PACKAGE_PREFIX . '0000000002',
                ],
            ]),
            CancelShipmentRequest::class => MockResponse::make([
                'message' => 'Envío anulado correctamente',
                'errors'  => null,
            ]),
            CancelExpeditionRequest::class => MockResponse::make([
                'message' => 'Expedición anulada correctamente',
                'errors'  => null,
            ]),
            GetBackofficeShipmentRequest::class => MockResponse::make(self::backoffice('CONSULTA_ENVIO')),
            GetBackofficeErrorsRequest::class   => MockResponse::make(self::backoffice('ERRORES')),
            GetBackofficeTotalRequest::class    => MockResponse::make(self::backoffice('TOTALES')),
            GetBackofficeWaitingRequest::class  => MockResponse::make(self::backoffice('PENDIENTES')),

            // Labels
            PrintLabelsRequest::class => fn(PendingRequest $request): MockResponse => MockResponse::make([
                'pdf' => base64_encode(FakePdf::make([
                    'ETIQUETA DE PRUEBA - Correos (modo sin conexion)',
                    'Envio: ' . self::firstShipmentCode($request),
                    'Fecha: ' . self::today(),
                    '',
                    'Esto no es una etiqueta valida: la respuesta viene',
                    'del fake local, no de Correos.',
                ])),
                'zpl'   => null,
                'xml'   => null,
                'error' => null,
            ]),
            PrintDocumentsRequest::class => MockResponse::make([
                'pdf' => base64_encode(FakePdf::make([
                    'DOCUMENTO DE ADUANAS DE PRUEBA (modo sin conexion)',
                    'Fecha: ' . self::today(),
                ])),
                'error' => null,
            ]),
            GetDocumentBackofficeRequest::class => MockResponse::make([
                'results' => [[
                    'entryDate'         => self::today(),
                    'shipment'          => self::SHIPMENT_PREFIX . '000000001',
                    'documentationType' => '1',
                    'request'           => '{"documentationType":1}',
                    'response'          => '{"pdf":"<base64>"}',
                ]],
                'error' => null,
            ]),

            // Tracking
            SearchShipmentRequest::class => fn(PendingRequest $request): MockResponse => MockResponse::make([
                'code'              => self::lastUrlSegment($request),
                'codProduct'        => 'PAFXB',
                'serviceDate'       => self::today(),
                'remitName'         => (string)config('correos.sender.name'),
                'destiName'         => 'Destinatario de prueba',
                'destiNameLocation' => 'Madrid',
                'totalPackage'      => '1',
                'weight'            => '500',
                'events'            => [
                    self::event('P010000V', 'Envío prerregistrado', 'CTA MADRID'),
                    self::event('P020000V', 'Admitido en oficina', 'OFICINA A CORUÑA'),
                ],
                'error' => null,
            ]),
            GetExpeditionRequest::class => fn(PendingRequest $request): MockResponse => MockResponse::make([
                'refExpedition'      => self::lastUrlSegment($request),
                'serviceCode'        => 'PAFXB',
                'serviceDescription' => 'Paq Premium',
                'entryDate'          => self::today(),
                'clients'            => [[
                    'clientCode'   => '0000000000',
                    'businessName' => (string)config('correos.sender.name'),
                    'locality'     => (string)config('correos.sender.locality'),
                    'postalCode'   => (string)config('correos.sender.cp'),
                    'country'      => 'ESP',
                ]],
                'packages' => [[
                    'shippingCode' => self::PACKAGE_PREFIX . '0000000001',
                    'number'       => '1',
                ]],
                'error' => null,
            ]),
        ]);
    }

    /**
     * Validate and create answer with the same shape, and both echo the payload
     * back: a code that has nothing to do with what was sent would hide a
     * request built wrong, which is the mistake this page exists to catch.
     *
     * @return array<string, mixed>
     */
    private static function delivery(PendingRequest $request, bool $validated): array
    {
        $shipment = self::sentShipment($request);
        $suffix = self::suffix((string)($shipment['packages'][0]['clientReference'] ?? ''));

        return [
            'fileIdentifier' => 'FAKE' . $suffix,
            'result'         => 0,
            'shipments'      => [[
                'validationErrorCount' => 0,
                'shipmentCode'         => $validated ? null : self::SHIPMENT_PREFIX . $suffix,
                'entryDate'            => self::today(),
                'packages'             => $validated ? null : [[
                    'packageId'   => '1',
                    'packageCode' => self::PACKAGE_PREFIX . $suffix,
                ]],
                'error' => null,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function backoffice(string $operation): array
    {
        return [
            'entryDate' => self::today(),
            'results'   => [[
                'entryDate' => self::today(),
                'operation' => $operation,
                'request'   => '{"fake":true}',
                'response'  => '{"fake":true}',
            ]],
            'error' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function event(string $code, string $summary, string $location): array
    {
        return [
            'eventDate'   => self::today(),
            'eventHours'  => now()->format('H:i'),
            'eventCode'   => $code,
            'summaryText' => $summary,
            'location'    => $location,
            'province'    => 'MADRID',
            'country'     => 'ESPAÑA',
        ];
    }

    /**
     * The first shipment of the payload that was actually sent.
     *
     * @return array<string, mixed>
     */
    private static function sentShipment(PendingRequest $request): array
    {
        $body = $request->body()?->all();

        $shipment = is_array($body) ? ($body['shipments'][0] ?? []) : [];

        return is_array($shipment) ? $shipment : [];
    }

    /**
     * Label and query requests carry the codes rather than a shipment, and the
     * page sends whatever is in its field -- including nothing at all.
     */
    private static function firstShipmentCode(PendingRequest $request): string
    {
        $body = $request->body()?->all();

        $code = is_array($body)
            ? ($body['print']['shipments'][0] ?? $body['shipments'][0] ?? null)
            : null;

        return is_string($code) && $code !== '' ? $code : self::SHIPMENT_PREFIX . '000000001';
    }

    /** Tracking puts the code in the path: /search/{code}, /expedition/{code}. */
    private static function lastUrlSegment(PendingRequest $request): string
    {
        return (string)Str::afterLast($request->getUrl(), '/');
    }

    /**
     * Ten digits derived from our own reference, so two runs of the same
     * reference come back with the same codes and two different ones do not.
     */
    private static function suffix(string $reference): string
    {
        $digits = preg_replace('/\D/', '', $reference) ?: (string)crc32($reference);

        return substr(str_pad($digits, 10, '0', STR_PAD_LEFT), -10);
    }

    private static function today(): string
    {
        return now()->format('d/m/Y');
    }
}
