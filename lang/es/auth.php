<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed'   => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Inténtalo de nuevo en :seconds segundos.',

    /*
    |--------------------------------------------------------------------------
    | Sign in to ask for a book
    |--------------------------------------------------------------------------
    |
    | Printed under the heading of the login and register pages when a reader
    | was sent there by the book request form, which is the only thing on the
    | shop behind a sign-in.
    |
    */

    'book_request' => [
        'reason' => 'Para pedirnos un libro hace falta una cuenta. En cuanto entres te devolvemos al formulario.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo
    |--------------------------------------------------------------------------
    |
    | The client panel's login form arrives with the shop's own address already
    | filled in; this is the riddle printed under the password box in its place.
    |
    */

    'demo' => [
        'password_hint' => 'La mujer que sabía quién era anónimo (en minúsculas y junto)',
    ],

];
