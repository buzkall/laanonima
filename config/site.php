<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop
    |--------------------------------------------------------------------------
    |
    | Where a reader who wants a book writes to. The book page has no basket:
    | every call to action on it is a mailto to this address, so it is the one
    | thing on the page that must never be a placeholder in production.
    |
    */

    'contact_email' => env('SITE_CONTACT_EMAIL', 'hola@laanonima.es'),

];
