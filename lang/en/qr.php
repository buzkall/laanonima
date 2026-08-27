<?php

return [

    'title'            => 'QR generator',
    'navigation_label' => 'QR generator',
    'subheading'       => 'Generate a code with the La Anónima question mark in the centre.',

    'fields' => [
        'url' => 'QR destination',
    ],

    'helpers' => [
        'url' => 'The address the code will lead to when scanned.',
    ],

    'placeholders' => [
        'preview' => 'Enter a valid URL to see the preview.',
    ],

    'actions' => [
        'download_svg'     => 'Download SVG',
        'download_thermal' => 'PNG for tickets',
    ],

    'sections' => [
        'printing_tips' => [
            'heading' => 'How to print it well',
            'items'   => [
                'thermal' => 'For the ticket printer always use the PNG (384 px for 58 mm, 576 px for 80 mm). Do not rescale it.',
                'vector'  => 'For shop windows, posters or bookmarks use the SVG: it scales without losing sharpness.',
                'test'    => 'Test every new code with two different phones before running off a batch.',
            ],
        ],
    ],

];
