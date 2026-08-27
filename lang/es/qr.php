<?php

return [

    'title'            => 'Generador de QR',
    'navigation_label' => 'Generador de QR',
    'subheading'       => 'Genera un código con la interrogación de La Anónima en el centro.',

    'fields' => [
        'url' => 'Destino del QR',
    ],

    'helpers' => [
        'url' => 'La dirección a la que llevará el código al escanearlo.',
    ],

    'placeholders' => [
        'preview' => 'Introduce una URL válida para ver la previsualización.',
    ],

    'actions' => [
        'download_svg'     => 'Descargar SVG',
        'download_thermal' => 'PNG para ticket',
    ],

    'sections' => [
        'printing_tips' => [
            'heading' => 'Cómo imprimirlo bien',
            'items'   => [
                'thermal' => 'Para la impresora de tickets usa siempre el PNG (384 px para 58 mm, 576 px para 80 mm). No lo reescales.',
                'vector'  => 'Para escaparate, cartelería o marcapáginas usa el SVG: escala sin perder nitidez.',
                'test'    => 'Prueba cada código nuevo con dos móviles distintos antes de tirar una remesa.',
            ],
        ],
    ],

];
