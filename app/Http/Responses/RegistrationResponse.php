<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;

/**
 * Sends a reader who has just signed up to their own panel.
 *
 * Registration signs the new user straight in, so it lands in exactly the trap
 * {@see LoginResponse} was written for: Filament's own response follows the
 * remembered intended URL, which the panels share and which may well be
 * `/admin` left behind by an earlier visit -- a 403 as a welcome. The rule is
 * the same one sign-in follows, so the response is the same one.
 */
class RegistrationResponse extends LoginResponse implements RegistrationResponseContract {}
