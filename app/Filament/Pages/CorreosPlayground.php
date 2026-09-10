<?php

namespace App\Filament\Pages;

use App\Support\Correos\FakeCorreos;
use Arzcode\LaravelCorreos\Correos;
use Arzcode\LaravelCorreos\Data\Labels\DocumentResponseData;
use Arzcode\LaravelCorreos\Data\Labels\LabelsResponseData;
use Arzcode\LaravelCorreos\Data\Labels\PrintDocumentsRequestData;
use Arzcode\LaravelCorreos\Data\Labels\PrintLabelsRequestData;
use Arzcode\LaravelCorreos\Data\Preregister\AnnulmentRequestData;
use Arzcode\LaravelCorreos\Data\Preregister\DeliveryRequestData;
use Arzcode\LaravelCorreos\Data\Preregister\GenerateShipmentCodeRequestData;
use Arzcode\LaravelCorreos\Data\Preregister\QueryRequestData;
use Arzcode\LaravelCorreos\Enums\DocumentationType;
use Arzcode\LaravelCorreos\Enums\LabelFormat;
use Arzcode\LaravelCorreos\Enums\LabelPrintMode;
use Arzcode\LaravelCorreos\Enums\ProductCode;
use Arzcode\LaravelCorreos\Exceptions\CorreosApiException;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Drives every method of the Correos SDK by hand, so the integration can be
 * proved against the pre-production environment before anything in the shop
 * depends on it.
 *
 * Reads are safe to repeat. The three writes -- creating a shipment,
 * generating codes, cancelling -- ask for confirmation because they land on
 * the real contract: pre-production books real preregistrations.
 *
 * @property-read Schema $form
 */
class CorreosPlayground extends Page
{
    /**
     * Everything DeliveryRequestData types as a plain string. Missing any of
     * them is a TypeError deep inside the DTO -- "the constructor requires 25
     * parameters, 21 given" -- rather than an answer from Correos, so the
     * payload is checked here before it is built.
     *
     * @var list<string>
     */
    protected const DELIVERY_FIELDS = [
        'product',
        'deliveryMethod',
        'contractNumber',
        'clientNumber',
        'labellerCode',
        'weightGrams',
        'sender.address',
        'sender.locality',
        'sender.province',
        'sender.cp',
        'sender.country',
        'addressee.address',
        'addressee.locality',
        'addressee.province',
        'addressee.cp',
        'addressee.country',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected string $view = 'filament.pages.correos-playground';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** The JSON body of the last call, pretty printed. */
    public ?string $output = null;

    public ?string $outputTitle = null;

    /**
     * The page books real shipments and shows raw API responses, neither of
     * which belongs in production. Not `isLocal()`: the test suite runs under
     * "testing" and has to reach the page.
     */
    public static function canAccess(): bool
    {
        return ! app()->isProduction();
    }

    public function mount(): void
    {
        $this->form->fill([
            'product'         => ProductCode::PaqPremium->value,
            'deliveryMethod'  => (string)config('correos.delivery_method'),
            'contractNumber'  => (string)config('correos.contract_number'),
            'clientNumber'    => (string)config('correos.client_number'),
            'labellerCode'    => (string)config('correos.labeller_code'),
            'weightGrams'     => '500',
            'clientReference' => 'PRUEBA-' . now()->format('YmdHis'),
            'sender'          => [
                'name'     => (string)config('correos.sender.name'),
                'address'  => (string)config('correos.sender.address'),
                'locality' => (string)config('correos.sender.locality'),
                'province' => (string)config('correos.sender.province'),
                'cp'       => (string)config('correos.sender.cp'),
                'country'  => (string)config('correos.sender.country'),
            ],
            'addressee' => [
                'name'     => 'Destinatario de prueba',
                'address'  => 'Calle Mayor 1',
                'locality' => 'Madrid',
                'province' => '28',
                'cp'       => '28001',
                'country'  => 'ESP',
            ],
            'documentationType' => DocumentationType::Label->value,
            'labelFormat'       => LabelFormat::PDF->value,
            'labelPrintMode'    => LabelPrintMode::A4->value,
            'destinationName'   => 'France',
        ]);
    }

    public static function getNavigationLabel(): string
    {
        return __('correos.navigation_label');
    }

    public function getTitle(): string
    {
        return __('correos.title');
    }

    public function getSubheading(): ?string
    {
        return __('correos.subheading');
    }

    /**
     * The endpoint the calls will hit, so a run against production is never a
     * surprise. It is the preregister URL because that is where the writes go.
     */
    public function getEnvironmentUrl(): string
    {
        return (string)config('laravel-correos.base_urls.preregister');
    }

    /**
     * Offline mode answers the SDK from memory. It has to be said on the page:
     * a green notification for a shipment that was never registered is worse
     * than no page at all.
     */
    public function isOffline(): bool
    {
        return FakeCorreos::enabled() && ! app()->isProduction();
    }

    /**
     * Correos issues two credential pairs and both are required; one missing
     * pair fails as a 401 that reads like the other one is wrong.
     */
    public function hasCredentials(): bool
    {
        return collect([
            'laravel-correos.oauth.client_id',
            'laravel-correos.oauth.client_secret',
            'laravel-correos.gateway.client_id',
            'laravel-correos.gateway.client_secret',
        ])->every(fn(string $key): bool => filled(config($key)));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('correos.sections.shipment.heading'))
                    ->description(__('correos.sections.shipment.description'))
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('product')
                                ->label(__('correos.fields.product'))
                                ->options(ProductCode::options())
                                ->searchable()
                                ->native(false),
                            $this->text('deliveryMethod'),
                            $this->text('contractNumber'),
                            $this->text('clientNumber'),
                            $this->text('labellerCode'),
                            $this->text('weightGrams'),
                            $this->text('clientReference')
                                ->helperText(__('correos.helpers.client_reference')),
                        ]),
                        Grid::make(2)->schema([
                            Section::make(__('correos.sections.sender.heading'))
                                ->schema($this->addressFields('sender'))
                                ->compact(),
                            Section::make(__('correos.sections.addressee.heading'))
                                ->schema($this->addressFields('addressee'))
                                ->compact(),
                        ]),
                    ])
                    ->footerActions([
                        $this->validateShipmentAction(),
                        $this->createShipmentAction(),
                        $this->generateShipmentCodeAction(),
                    ]),

                Section::make(__('correos.sections.labels.heading'))
                    ->description(__('correos.sections.labels.description'))
                    ->schema([
                        Grid::make(3)->schema([
                            $this->text('shipmentCode'),
                            Select::make('documentationType')
                                ->label(__('correos.fields.documentationType'))
                                ->options($this->translatedOptions(DocumentationType::class, 'documentation_type'))
                                ->native(false),
                            Select::make('labelFormat')
                                ->label(__('correos.fields.labelFormat'))
                                ->options(LabelFormat::options())
                                ->native(false),
                            Select::make('labelPrintMode')
                                ->label(__('correos.fields.labelPrintMode'))
                                ->options($this->translatedOptions(LabelPrintMode::class, 'label_print_mode'))
                                ->helperText(__('correos.helpers.label_print_mode'))
                                ->native(false),
                            $this->text('destinationName')
                                ->helperText(__('correos.helpers.destination_name')),
                        ]),
                    ])
                    ->footerActions([
                        $this->printLabelAction(),
                        $this->printDocumentAction(),
                        $this->documentBackofficeAction(),
                    ]),

                Section::make(__('correos.sections.tracking.heading'))
                    ->description(__('correos.sections.tracking.description'))
                    ->schema([
                        Grid::make(3)->schema([
                            $this->text('packageCode')
                                ->helperText(__('correos.helpers.package_code')),
                            $this->text('expeditionCode'),
                        ]),
                    ])
                    ->footerActions([
                        $this->trackAction(),
                        $this->expeditionAction(),
                        $this->queryShipmentsAction(),
                        $this->packagesByReferenceAction(),
                        $this->expeditionPackagesAction(),
                        $this->cancelShipmentAction(),
                    ]),

                Section::make(__('correos.sections.backoffice.heading'))
                    ->description(__('correos.sections.backoffice.description'))
                    ->schema([
                        Grid::make(3)->schema([
                            $this->text('dateFrom')
                                ->helperText(__('correos.helpers.dates')),
                            $this->text('dateTo'),
                        ]),
                    ])
                    ->footerActions([
                        $this->backofficeShipmentAction(),
                        $this->backofficeErrorsAction(),
                        $this->backofficeWaitingAction(),
                        $this->backofficeTotalAction(),
                    ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Preregister
    |--------------------------------------------------------------------------
    */

    /**
     * The only call that exercises the whole auth path -- OAuth token, gateway
     * headers, real contract -- without creating anything.
     */
    public function validateShipmentAction(): Action
    {
        return Action::make('validateShipment')
            ->label(__('correos.actions.validate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->action(fn(): bool => $this->requires(...self::DELIVERY_FIELDS) && $this->run(
                __('correos.actions.validate'),
                fn(Correos $correos): Data => $correos->preregister()->validateShipments($this->deliveryRequest()),
            ));
    }

    public function createShipmentAction(): Action
    {
        return Action::make('createShipment')
            ->label(__('correos.actions.create'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('correos.confirmations.create'))
            ->action(fn(): bool => $this->requires(...self::DELIVERY_FIELDS) && $this->run(
                __('correos.actions.create'),
                function(Correos $correos): Data {
                    $response = $correos->preregister()->createShipments($this->deliveryRequest());

                    // Carry the codes over so the label and tracking calls have
                    // something to work with without a copy and paste.
                    $shipment = $response->shipments[0] ?? null;

                    $this->data['shipmentCode'] = $shipment->shipmentCode ?? '';
                    $this->data['packageCode'] = $shipment->packages[0]->packageCode ?? '';

                    return $response;
                },
            ));
    }

    public function generateShipmentCodeAction(): Action
    {
        return Action::make('generateShipmentCode')
            ->label(__('correos.actions.generate_code'))
            ->icon(Heroicon::OutlinedHashtag)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('correos.confirmations.generate_code'))
            ->action(fn(): bool => $this->requires('contractNumber', 'clientNumber', 'labellerCode', 'product', 'deliveryMethod') && $this->run(
                __('correos.actions.generate_code'),
                fn(Correos $correos): Data => $correos->preregister()->generateShipmentCode(
                    GenerateShipmentCodeRequestData::from($this->strip([
                        'contractNumber' => $this->value('contractNumber'),
                        'clientNumber'   => $this->value('clientNumber'),
                        'labellerCode'   => $this->value('labellerCode'),
                        'packagesNumber' => '1',
                        'product'        => $this->value('product'),
                        'deliveryMethod' => $this->value('deliveryMethod'),
                    ])),
                ),
            ));
    }

    public function queryShipmentsAction(): Action
    {
        return Action::make('queryShipments')
            ->label(__('correos.actions.query'))
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('gray')
            ->action(fn(): bool => $this->requires('shipmentCode') && $this->run(
                __('correos.actions.query'),
                fn(Correos $correos): Data => $correos->preregister()->queryShipments(
                    QueryRequestData::from(['shipments' => [$this->value('shipmentCode')]]),
                ),
            ));
    }

    /**
     * The right answer to a create that timed out: ask Correos what it holds
     * under our own reference instead of sending the write again.
     */
    public function packagesByReferenceAction(): Action
    {
        return Action::make('packagesByReference')
            ->label(__('correos.actions.by_reference'))
            ->icon(Heroicon::OutlinedTag)
            ->color('gray')
            ->action(fn(): bool => $this->requires('clientReference') && $this->run(
                __('correos.actions.by_reference'),
                fn(Correos $correos): Data => $correos->preregister()->getPackagesByReference(
                    $this->value('clientReference'),
                    $this->value('contractNumber') ?: null,
                    $this->value('clientNumber') ?: null,
                ),
            ));
    }

    public function expeditionPackagesAction(): Action
    {
        return Action::make('expeditionPackages')
            ->label(__('correos.actions.expedition_packages'))
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('gray')
            ->action(fn(): bool => $this->requires('expeditionCode') && $this->run(
                __('correos.actions.expedition_packages'),
                fn(Correos $correos): Data => $correos->preregister()->getExpeditionPackages(
                    $this->value('expeditionCode'),
                ),
            ));
    }

    public function cancelShipmentAction(): Action
    {
        return Action::make('cancelShipment')
            ->label(__('correos.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('correos.confirmations.cancel'))
            ->action(fn(): bool => $this->requires('packageCode') && $this->run(
                __('correos.actions.cancel'),
                fn(Correos $correos): Data => $correos->preregister()->cancelShipment(
                    AnnulmentRequestData::from(['packageCode' => $this->value('packageCode')]),
                ),
            ));
    }

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    */

    /**
     * Returns the PDF rather than dumping it into the output panel: a label
     * you cannot look at proves nothing.
     */
    public function printLabelAction(): Action
    {
        return Action::make('printLabel')
            ->label(__('correos.actions.print_label'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(function(): ?StreamedResponse {
                if (! $this->requires('shipmentCode')) {
                    return null;
                }

                $response = $this->attempt(
                    __('correos.actions.print_label'),
                    fn(Correos $correos): LabelsResponseData => $correos->labels()->printLabels(PrintLabelsRequestData::from([
                        'documentationType' => (int)$this->value('documentationType'),
                        'print'             => [
                            'shipments'      => [$this->value('shipmentCode')],
                            'labelFormat'    => (int)$this->value('labelFormat'),
                            'labelPrintMode' => (int)$this->value('labelPrintMode'),
                        ],
                    ])),
                );

                if (! $response instanceof Data) {
                    return null;
                }

                $this->show(__('correos.actions.print_label'), $response);

                return $this->download($response->decodedPdf(), 'etiqueta-' . $this->value('shipmentCode'));
            });
    }

    public function printDocumentAction(): Action
    {
        return Action::make('printDocument')
            ->label(__('correos.actions.print_document'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->action(function(): ?StreamedResponse {
                $response = $this->attempt(
                    __('correos.actions.print_document'),
                    fn(Correos $correos): DocumentResponseData => $correos->labels()->printDocuments(PrintDocumentsRequestData::from($this->strip([
                        'documentationType' => (int)$this->value('documentationType'),
                        'documentData'      => [
                            'destinationName' => $this->value('destinationName'),
                            'contractNumber'  => $this->value('contractNumber'),
                            'clientNumber'    => $this->value('clientNumber'),
                        ],
                    ]))),
                );

                if (! $response instanceof Data) {
                    return null;
                }

                $this->show(__('correos.actions.print_document'), $response);

                return $this->download(
                    is_string($response->pdf) ? base64_decode($response->pdf) : null,
                    'documento-' . $this->value('documentationType'),
                );
            });
    }

    public function documentBackofficeAction(): Action
    {
        return Action::make('documentBackoffice')
            ->label(__('correos.actions.document_backoffice'))
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->color('gray')
            ->action(fn(): bool => $this->requires('shipmentCode') && $this->run(
                __('correos.actions.document_backoffice'),
                fn(Correos $correos): Data => $correos->labels()->getDocumentBackoffice(
                    $this->value('shipmentCode'),
                ),
            ));
    }

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    */

    public function trackAction(): Action
    {
        return Action::make('track')
            ->label(__('correos.actions.track'))
            ->icon(Heroicon::OutlinedMapPin)
            ->color('gray')
            ->action(fn(): bool => $this->requires('packageCode') && $this->run(
                __('correos.actions.track'),
                fn(Correos $correos): Data => $correos->tracking()->searchShipment($this->value('packageCode')),
            ));
    }

    public function expeditionAction(): Action
    {
        return Action::make('expedition')
            ->label(__('correos.actions.expedition'))
            ->icon(Heroicon::OutlinedTruck)
            ->color('gray')
            ->action(fn(): bool => $this->requires('expeditionCode') && $this->run(
                __('correos.actions.expedition'),
                fn(Correos $correos): Data => $correos->tracking()->getExpedition($this->value('expeditionCode')),
            ));
    }

    /*
    |--------------------------------------------------------------------------
    | Backoffice
    |--------------------------------------------------------------------------
    */

    public function backofficeShipmentAction(): Action
    {
        return Action::make('backofficeShipment')
            ->label(__('correos.actions.backoffice_shipment'))
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->color('gray')
            ->action(fn(): bool => $this->requires('shipmentCode') && $this->run(
                __('correos.actions.backoffice_shipment'),
                fn(Correos $correos): Data => $correos->preregister()->getBackofficeShipment(
                    $this->value('shipmentCode'),
                ),
            ));
    }

    public function backofficeErrorsAction(): Action
    {
        return $this->backofficeAction('backofficeErrors', __('correos.actions.backoffice_errors'), 'getBackofficeErrors');
    }

    public function backofficeWaitingAction(): Action
    {
        return $this->backofficeAction('backofficeWaiting', __('correos.actions.backoffice_waiting'), 'getBackofficeWaiting');
    }

    public function backofficeTotalAction(): Action
    {
        return $this->backofficeAction('backofficeTotal', __('correos.actions.backoffice_total'), 'getBackofficeTotal');
    }

    /*
    |--------------------------------------------------------------------------
    | Plumbing
    |--------------------------------------------------------------------------
    */

    /**
     * The three date-ranged backoffice queries take the same four arguments,
     * so they are one builder rather than three near-identical actions.
     */
    protected function backofficeAction(string $name, string $label, string $method): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->action(fn(): bool => $this->run($label, fn(Correos $correos): Data => $correos->preregister()->{$method}(
                $this->value('contractNumber') ?: null,
                $this->value('clientNumber') ?: null,
                $this->value('dateFrom') ?: null,
                $this->value('dateTo') ?: null,
            )));
    }

    /**
     * Runs a call and renders whatever came back, so the error handling is
     * written once.
     *
     * @param  Closure(Correos): Data  $callback
     */
    protected function run(string $label, Closure $callback): bool
    {
        $result = $this->attempt($label, $callback);

        if (! $result instanceof Data) {
            return false;
        }

        $this->show($label, $result);

        return true;
    }

    /**
     * Every failure mode ends up here. A Correos error carries a response to
     * show; a timeout or a refused connection -- the shape an IP that is not
     * whitelisted takes -- carries nothing but its message.
     */
    /**
     * @template TResponse of Data
     *
     * @param  Closure(Correos): TResponse  $callback
     * @return TResponse|null
     */
    protected function attempt(string $label, Closure $callback): ?Data
    {
        try {
            $result = $callback(app(Correos::class));

            Notification::make()
                ->success()
                ->title(__('correos.notifications.ok', ['call' => $label]))
                ->send();

            return $result;
        } catch (CorreosApiException $exception) {
            $response = $exception->getResponse();

            $this->outputTitle = __('correos.notifications.failed', ['call' => $label]);
            $this->output = $response->body();

            Notification::make()
                ->danger()
                ->title(__('correos.notifications.failed_with_status', ['call' => $label, 'status' => $response->status()]))
                ->body($exception->moreInformation ?? $exception->errorCode ?? $exception->getMessage())
                ->persistent()
                ->send();
        } catch (Throwable $exception) {
            $this->outputTitle = __('correos.notifications.failed', ['call' => $label]);
            $this->output = $exception::class . ': ' . $exception->getMessage();

            Notification::make()
                ->danger()
                ->title(__('correos.notifications.failed', ['call' => $label]))
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }

        return null;
    }

    protected function show(string $label, Data $result): void
    {
        $this->outputTitle = $label;
        $this->output = json_encode(
            $result->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: null;
    }

    /**
     * Correos answers part of its surface with a 200 and an empty document, so
     * a call that succeeded is not proof there is a PDF to hand over.
     */
    protected function download(?string $pdf, string $name): ?StreamedResponse
    {
        if ($pdf === null || $pdf === '') {
            Notification::make()
                ->warning()
                ->title(__('correos.notifications.no_pdf'))
                ->body(__('correos.notifications.no_pdf_body'))
                ->send();

            return null;
        }

        return response()->streamDownload(
            fn(): int => print $pdf,
            $name . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    protected function deliveryRequest(): DeliveryRequestData
    {
        return DeliveryRequestData::from($this->strip([
            'shipments' => [[
                'product'        => $this->value('product'),
                'deliveryMethod' => $this->value('deliveryMethod'),
                'contractNumber' => $this->value('contractNumber'),
                'clientNumber'   => $this->value('clientNumber'),
                'labellerCode'   => $this->value('labellerCode'),
                'packagesNumber' => '1',
                'sender'         => Arr::get($this->data, 'sender', []),
                'addressee'      => Arr::get($this->data, 'addressee', []),
                'packages'       => [[
                    'packageWeightGrams' => $this->value('weightGrams'),
                    'clientReference'    => $this->value('clientReference'),
                ]],
            ]],
        ]));
    }

    /**
     * Stops a call the payload cannot carry before it leaves, rather than
     * letting Correos answer it with an error that says less.
     */
    protected function requires(string ...$keys): bool
    {
        $missing = Collection::make($keys)
            ->reject(fn(string $key): bool => filled($this->value($key)))
            ->map($this->fieldLabel(...))
            ->all();

        if ($missing === []) {
            return true;
        }

        Notification::make()
            ->warning()
            ->title(__('correos.notifications.missing_fields'))
            ->body(implode(', ', $missing))
            ->send();

        return false;
    }

    /**
     * "sender.cp" has no label of its own: it is the postcode field of the
     * sender section, and a warning that only says "postcode" would leave you
     * looking at two of them.
     */
    protected function fieldLabel(string $key): string
    {
        if (! str_contains($key, '.')) {
            return __("correos.fields.{$key}");
        }

        [$section, $field] = explode('.', $key, 2);

        return __("correos.sections.{$section}.heading") . ' · ' . __("correos.fields.{$field}");
    }

    protected function value(string $key): string
    {
        $value = Arr::get($this->data, $key);

        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * @return array<int, TextInput>
     */
    protected function addressFields(string $prefix): array
    {
        return array_map(
            fn(string $field): TextInput => $this->text("{$prefix}.{$field}", $field),
            ['name', 'address', 'locality', 'province', 'cp', 'country'],
        );
    }

    /**
     * The SDK's enum labels are English. The product names are the commercial
     * ones Correos itself uses and read fine here; these two lists do not.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return array<int|string, string>
     */
    protected function translatedOptions(string $enum, string $group): array
    {
        return Collection::make($enum::cases())
            ->mapWithKeys(fn(BackedEnum $case): array => [$case->value => __("correos.options.{$group}.{$case->name}")])
            ->all();
    }

    protected function text(string $name, ?string $label = null): TextInput
    {
        return TextInput::make($name)->label(__('correos.fields.' . ($label ?? $name)));
    }

    /**
     * Optional DTO properties are typed `string|Optional`, so an untouched
     * field arriving as null is a TypeError rather than a validation error.
     * Empties are stripped recursively, nested payloads included.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function strip(array $values): array
    {
        return Collection::make($values)
            ->map(fn($value) => is_array($value) ? $this->strip($value) : $value)
            ->reject(fn($value): bool => in_array($value, [null, '', []], true))
            ->all();
    }
}
