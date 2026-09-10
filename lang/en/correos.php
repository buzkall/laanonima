<?php

return [

    'title'            => 'Correos playground',
    'navigation_label' => 'Correos playground',
    'subheading'       => 'Drive every Correos SDK call against the configured environment.',

    'credentials' => [
        'heading' => 'Missing credentials',
        'body'    => 'Correos issues two credential pairs and both are required: CORREOS_OAUTH_CLIENT_ID and CORREOS_OAUTH_CLIENT_SECRET mint the token, CORREOS_GATEWAY_CLIENT_ID and CORREOS_GATEWAY_CLIENT_SECRET are sent as headers. With either missing, everything answers 401.',
    ],

    'fake' => [
        'heading' => 'Offline mode',
        'body'    => 'CORREOS_FAKE is on: the responses are made up by the application and no call leaves this machine. It is here to walk the whole page without credentials — nothing you see here exists at Correos.',
    ],

    'environment' => [
        'heading' => 'Environment',
        'body'    => 'Calls go to:',
    ],

    'sections' => [
        'shipment' => [
            'heading'     => 'Shipment',
            'description' => 'Validate creates nothing and can be repeated. Create registers a real shipment on the contract.',
        ],
        'sender' => [
            'heading' => 'Sender',
        ],
        'addressee' => [
            'heading' => 'Addressee',
        ],
        'labels' => [
            'heading'     => 'Labels and documents',
            'description' => 'The label downloads as a PDF; the rest of the response shows up below.',
        ],
        'tracking' => [
            'heading'     => 'Tracking and queries',
            'description' => 'Tracking works on the package code, not the shipment code.',
        ],
        'backoffice' => [
            'heading'     => 'Backoffice',
            'description' => 'Read-only views of what Correos holds for the contract.',
        ],
    ],

    'fields' => [
        'product'           => 'Product',
        'deliveryMethod'    => 'Delivery method',
        'contractNumber'    => 'Contract number',
        'clientNumber'      => 'Client number',
        'labellerCode'      => 'Labeller code',
        'weightGrams'       => 'Weight (grams)',
        'clientReference'   => 'Client reference',
        'name'              => 'Name',
        'address'           => 'Address',
        'locality'          => 'Locality',
        'province'          => 'Province',
        'cp'                => 'Postcode',
        'country'           => 'Country',
        'shipmentCode'      => 'Shipment code',
        'packageCode'       => 'Package code',
        'expeditionCode'    => 'Expedition code',
        'documentationType' => 'Document type',
        'labelFormat'       => 'Label format',
        'labelPrintMode'    => 'Print mode',
        'destinationName'   => 'Destination country',
        'dateFrom'          => 'From',
        'dateTo'            => 'To',
    ],

    'options' => [
        'documentation_type' => [
            'All'       => 'All documents',
            'Label'     => 'Label',
            'CN22_CN23' => 'CN22/CN23 customs form',
            'DCAF'      => 'DCAF',
            'DDP'       => 'DDP',
        ],
        'label_print_mode' => [
            'A4'      => 'A4 sheet',
            'Labeler' => 'Labeler',
        ],
    ],

    'helpers' => [
        'client_reference' => 'The reference to reconcile a create that never answered.',
        'label_print_mode' => 'A4 returns the sheet already laid out; Labeler, one label per page.',
        'destination_name' => 'DCAF and DDP only.',
        'package_code'     => 'Tracking and cancellation work on the package, not the shipment.',
        'dates'            => 'Format dd/mm/yyyy.',
    ],

    'actions' => [
        'validate'            => 'Validate',
        'create'              => 'Create shipment',
        'generate_code'       => 'Generate codes',
        'query'               => 'Query shipment',
        'by_reference'        => 'Search by reference',
        'expedition_packages' => 'Expedition packages',
        'cancel'              => 'Cancel package',
        'print_label'         => 'Download label',
        'print_document'      => 'Download document',
        'document_backoffice' => 'Shipment documents',
        'track'               => 'Track',
        'expedition'          => 'Expedition',
        'backoffice_shipment' => 'Shipment in backoffice',
        'backoffice_errors'   => 'Errors',
        'backoffice_waiting'  => 'Waiting',
        'backoffice_total'    => 'Totals',
    ],

    'confirmations' => [
        'create'        => 'This registers a real shipment on the Correos contract. If the call never answers, do not repeat it: use "Search by reference" to see what was registered.',
        'generate_code' => 'This consumes real codes from the contract.',
        'cancel'        => 'This cancels the package at Correos.',
    ],

    'output' => [
        'heading' => 'Response',
        'empty'   => 'The response of the last call shows up here.',
    ],

    'notifications' => [
        'ok'                 => ':call: OK',
        'failed'             => ':call: failed',
        'failed_with_status' => ':call: failed (:status)',
        'missing_fields'     => 'This call is missing data',
        'no_pdf'             => 'No PDF in the response',
        'no_pdf_body'        => 'The call succeeded but carried no document. Check the shipment code.',
    ],

];
