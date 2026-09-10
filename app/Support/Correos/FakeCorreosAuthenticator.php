<?php

namespace App\Support\Correos;

use Saloon\Http\PendingRequest;
use SmartDato\CorreosShipping\Auth\CorreosAuthenticator;

/**
 * Signs requests without minting a token.
 *
 * Saloon builds the pending request in full before the mock sender answers it,
 * so the real authenticator would still go out to CorreosID for a token that is
 * never used -- and fail, since offline mode exists precisely because there are
 * no credentials. The connectors type-hint the concrete class, so this extends
 * it rather than implementing Saloon's interface.
 */
class FakeCorreosAuthenticator extends CorreosAuthenticator
{
    public function __construct()
    {
        parent::__construct(
            oauthClientId: 'fake',
            oauthClientSecret: 'fake',
            tokenUrl: 'https://correos.invalid/token',
            scope: 'AP3 LBS RCG',
            gatewayClientId: 'fake',
            gatewayClientSecret: 'fake',
        );
    }

    public function set(PendingRequest $pendingRequest): void
    {
        $pendingRequest->headers()->add('Authorization', 'Bearer fake-token');
        $pendingRequest->headers()->add('client_id', 'fake');
        $pendingRequest->headers()->add('client_secret', 'fake');
    }
}
