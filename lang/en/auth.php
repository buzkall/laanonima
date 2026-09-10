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

    'failed'   => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

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
        'reason' => 'Asking us for a book needs an account. As soon as you are in we take you back to the form.',
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
        'password_hint' => 'the woman who knew who Anonymous was (lowercase, no spaces)',
    ],

];
