<?php

return [

    'title'            => 'Pruebas de Correos',
    'navigation_label' => 'Pruebas de Correos',
    'subheading'       => 'Ejercita cada llamada del SDK de Correos contra el entorno configurado.',

    'credentials' => [
        'heading' => 'Faltan credenciales',
        'body'    => 'Correos entrega dos pares de credenciales y hacen falta los dos: CORREOS_OAUTH_CLIENT_ID y CORREOS_OAUTH_CLIENT_SECRET para el token, CORREOS_GATEWAY_CLIENT_ID y CORREOS_GATEWAY_CLIENT_SECRET para las cabeceras. Sin uno de ellos todo responde 401.',
    ],

    'fake' => [
        'heading' => 'Modo sin conexión',
        'body'    => 'CORREOS_FAKE está activado: las respuestas las inventa la aplicación y ninguna llamada sale de esta máquina. Sirve para recorrer la página entera sin credenciales — nada de lo que aparezca aquí existe en Correos.',
    ],

    'environment' => [
        'heading' => 'Entorno',
        'body'    => 'Las llamadas van contra:',
    ],

    'sections' => [
        'shipment' => [
            'heading'     => 'Envío',
            'description' => 'Validar no crea nada y se puede repetir. Crear registra un envío real en el contrato.',
        ],
        'sender' => [
            'heading' => 'Remitente',
        ],
        'addressee' => [
            'heading' => 'Destinatario',
        ],
        'labels' => [
            'heading'     => 'Etiquetas y documentos',
            'description' => 'La etiqueta se descarga como PDF; el resto de la respuesta aparece abajo.',
        ],
        'tracking' => [
            'heading'     => 'Seguimiento y consultas',
            'description' => 'El seguimiento va por código de bulto, no por código de envío.',
        ],
        'backoffice' => [
            'heading'     => 'Backoffice',
            'description' => 'Consultas de solo lectura sobre lo que Correos tiene registrado del contrato.',
        ],
    ],

    'fields' => [
        'product'           => 'Producto',
        'deliveryMethod'    => 'Modalidad de entrega',
        'contractNumber'    => 'Número de contrato',
        'clientNumber'      => 'Número de cliente',
        'labellerCode'      => 'Código de etiquetadora',
        'weightGrams'       => 'Peso (gramos)',
        'clientReference'   => 'Referencia propia',
        'name'              => 'Nombre',
        'address'           => 'Dirección',
        'locality'          => 'Localidad',
        'province'          => 'Provincia',
        'cp'                => 'Código postal',
        'country'           => 'País',
        'shipmentCode'      => 'Código de envío',
        'packageCode'       => 'Código de bulto',
        'expeditionCode'    => 'Código de expedición',
        'documentationType' => 'Tipo de documento',
        'labelFormat'       => 'Formato de etiqueta',
        'labelPrintMode'    => 'Modo de impresión',
        'destinationName'   => 'País de destino',
        'dateFrom'          => 'Desde',
        'dateTo'            => 'Hasta',
    ],

    'options' => [
        'documentation_type' => [
            'All'       => 'Todos los documentos',
            'Label'     => 'Etiqueta',
            'CN22_CN23' => 'Declaración de aduanas CN22/CN23',
            'DCAF'      => 'DCAF',
            'DDP'       => 'DDP',
        ],
        'label_print_mode' => [
            'A4'      => 'Hoja A4',
            'Labeler' => 'Etiquetadora',
        ],
    ],

    'helpers' => [
        'client_reference' => 'La referencia con la que reconciliar un alta que se quedó sin respuesta.',
        'label_print_mode' => 'A4 devuelve la hoja montada; Etiquetadora, una etiqueta por página.',
        'destination_name' => 'Solo para DCAF y DDP.',
        'package_code'     => 'El seguimiento y la anulación trabajan con el bulto, no con el envío.',
        'dates'            => 'Formato dd/mm/aaaa.',
    ],

    'actions' => [
        'validate'            => 'Validar',
        'create'              => 'Crear envío',
        'generate_code'       => 'Generar códigos',
        'query'               => 'Consultar envío',
        'by_reference'        => 'Buscar por referencia',
        'expedition_packages' => 'Bultos de la expedición',
        'cancel'              => 'Anular bulto',
        'print_label'         => 'Descargar etiqueta',
        'print_document'      => 'Descargar documento',
        'document_backoffice' => 'Documentos del envío',
        'track'               => 'Seguimiento',
        'expedition'          => 'Expedición',
        'backoffice_shipment' => 'Envío en backoffice',
        'backoffice_errors'   => 'Errores',
        'backoffice_waiting'  => 'Pendientes',
        'backoffice_total'    => 'Totales',
    ],

    'confirmations' => [
        'create'        => 'Esto registra un envío real en el contrato de Correos. Si la llamada se queda sin respuesta, no la repitas: usa «Buscar por referencia» para ver qué quedó registrado.',
        'generate_code' => 'Esto consume códigos reales del contrato.',
        'cancel'        => 'Esto anula el bulto en Correos.',
    ],

    'output' => [
        'heading' => 'Respuesta',
        'empty'   => 'Aquí aparecerá la respuesta de la última llamada.',
    ],

    'notifications' => [
        'ok'                 => ':call: correcto',
        'failed'             => ':call: ha fallado',
        'failed_with_status' => ':call: ha fallado (:status)',
        'missing_fields'     => 'Faltan datos para esta llamada',
        'no_pdf'             => 'La respuesta no traía PDF',
        'no_pdf_body'        => 'La llamada funcionó pero no vino ningún documento. Revisa el código de envío.',
    ],

];
