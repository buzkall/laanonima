<?php

/*
 | Filament's own Spanish addresses the reader as "usted". This shop speaks to
 | its readers as "tú", so every string that talks to them is overridden here.
 |
 | Laravel merges a vendor override into the package's file with
 | array_replace_recursive, so only the keys that change are listed: everything
 | else keeps tracking upstream and arrives translated after an upgrade.
 */

return [

    'heading' => 'Entra a tu cuenta',

    'actions' => [

        'request_password_reset' => [
            'label' => '¿Has olvidado tu contraseña?',
        ],

    ],

    'multi_factor' => [

        'heading' => 'Verifica tu identidad',

        'subheading' => 'Para seguir con el inicio de sesión, tienes que verificar tu identidad.',

        'form' => [

            'provider' => [
                'label' => '¿Cómo quieres verificarla?',
            ],

        ],

    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Demasiados intentos. Prueba de nuevo en :seconds segundos.',
            'body' => 'Prueba de nuevo en :seconds segundos.',
        ],

    ],

];
