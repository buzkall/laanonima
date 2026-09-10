<?php

return [
    'oauth' => [
        'client_id'     => env('CORREOS_OAUTH_CLIENT_ID'),
        'client_secret' => env('CORREOS_OAUTH_CLIENT_SECRET'),
        'token_url'     => env('CORREOS_TOKEN_URL', 'https://apioauthcid.correos.es/Api/Authorize/Token'),
        'scope'         => env('CORREOS_OAUTH_SCOPE', 'AP3 LBS RCG'),
    ],
    'gateway' => [
        'client_id'     => env('CORREOS_GATEWAY_CLIENT_ID'),
        'client_secret' => env('CORREOS_GATEWAY_CLIENT_SECRET'),
    ],
    'base_urls' => [
        'preregister' => env('CORREOS_PREREGISTER_URL', 'https://api1.correos.es/admissions/preregister/api/v1'),
        'labels'      => env('CORREOS_LABELS_URL', 'https://api1.correos.es/support/labels/api/v1'),
        'tracking'    => env('CORREOS_TRACKING_URL', 'https://api1.correos.es/support/trackpub/api/v2'),
    ],
    'verify_ssl'       => env('CORREOS_VERIFY_SSL', true),
    'force_ip_resolve' => env('CORREOS_FORCE_IP_RESOLVE'),

    /*
     * Transient failures (gateway rate limiting, connection errors) are retried.
     * Calls that could already have been processed by Correos are not — see the
     * retry policy in CorreosConnector::handleRetry(). Set `times` to 1 to
     * disable retries altogether.
     */
    'retry' => [
        'times'               => env('CORREOS_RETRY_TIMES', 3),
        'interval'            => env('CORREOS_RETRY_INTERVAL', 500), // milliseconds
        'exponential_backoff' => env('CORREOS_RETRY_EXPONENTIAL_BACKOFF', true),
    ],

    /*
     * Guzzle timeouts, in seconds. Left null, Saloon's defaults apply (30s for
     * the request, 10s to connect). Lower them on interactive paths, where a
     * user is waiting for the answer.
     */
    'timeout'         => env('CORREOS_TIMEOUT'),
    'connect_timeout' => env('CORREOS_CONNECT_TIMEOUT'),

    /*
     * Overrides the User-Agent header sent to Correos. Defaults to the SDK name
     * and the installed package version.
     */
    'user_agent' => env('CORREOS_USER_AGENT'),
];
