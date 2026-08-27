<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QR Code Generation
    |--------------------------------------------------------------------------
    |
    | The isotipo sits in the middle of the code and destroys the modules it
    | covers, so error correction is always High and the logo never grows past
    | "logo_ratio" of the code's side.
    |
    | Sweeping URL lengths from 29 to 172 characters at both printer widths,
    | every code still decodes at 0.26 and the first failures appear at 0.28.
    | 0.25 keeps a step of headroom for the real world, where a phone camera
    | reads smudged thermal paper rather than a clean file.
    |
    */

    'logo_ratio' => 0.25,

    'logo_padding' => 0.10,

    'quiet_modules' => 4,

    'assets' => [
        'svg' => resource_path('images/brand/interrogacion-mono.svg'),
        'png' => resource_path('images/brand/interrogacion-mono.png'),
    ],

    /*
    | Real usable widths of the shop's thermal printers at 203 dpi, in pixels.
    | The PNG must be produced at exactly one of these; rescaling afterwards
    | blurs the module edges and the code stops scanning.
    */

    'thermal' => [
        'width_58mm' => 384,
        'width_80mm' => 576,
        'default'    => 384,
    ],

];
