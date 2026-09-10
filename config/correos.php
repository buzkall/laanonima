<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offline mode
    |--------------------------------------------------------------------------
    |
    | Correos has no sandbox anyone can sign up for: pre-production credentials
    | come from a commercial contact, and the calls only answer from an IPv4
    | that Correos has whitelisted, on weekdays. With this on, the SDK is
    | answered from memory by App\Support\Correos\FakeCorreos -- the payload is
    | still built, serialized and hydrated, only the network is faked -- so the
    | panel's Correos page can be driven end to end without credentials.
    |
    | It is ignored in production, and turning it on there would be a mistake
    | rather than a test: shipments would look booked and never exist.
    |
    */

    'fake' => env('CORREOS_FAKE', false),

    /*
    |--------------------------------------------------------------------------
    | Contract
    |--------------------------------------------------------------------------
    |
    | The three numbers Correos hands over with the commercial agreement. The
    | SDK does not read them -- every call carries them in the payload -- so
    | they live here rather than in config/correos-shipping-sdk.php, which is
    | the package's own file and is overwritten by a re-publish.
    |
    | They only prefill the diagnostics page today. Anything that books a real
    | shipment later should read them from here too instead of typing them
    | again.
    |
    */

    'contract_number' => env('CORREOS_CONTRACT_NUMBER'),

    'client_number' => env('CORREOS_CLIENT_NUMBER'),

    'labeller_code' => env('CORREOS_LABELLER_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Delivery method
    |--------------------------------------------------------------------------
    |
    | DOUAOF is delivery at the addressee's door with handover at a post
    | office, which is what the shop does. It is a plain string in the API and
    | not one of the SDK's enums, because the list depends on the contract.
    |
    */

    'delivery_method' => env('CORREOS_DELIVERY_METHOD', 'DOUAOF'),

    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    |
    | Where the parcels leave from. "province" is the two-digit code of the
    | postcode, not the name, and "country" is the three-letter ISO code --
    | Correos rejects both in any other shape.
    |
    | Everything but the country is a placeholder: the shop's real address is
    | not in the repository, so set it in .env before booking anything.
    |
    */

    'sender' => [
        'name'     => env('CORREOS_SENDER_NAME', env('APP_NAME', 'La Anónima')),
        'address'  => env('CORREOS_SENDER_ADDRESS', ''),
        'locality' => env('CORREOS_SENDER_LOCALITY', ''),
        'province' => env('CORREOS_SENDER_PROVINCE', ''),
        'cp'       => env('CORREOS_SENDER_CP', ''),
        'country'  => env('CORREOS_SENDER_COUNTRY', 'ESP'),
    ],

];
