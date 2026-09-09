<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The one description of what counts as an acceptable password here.
 *
 * Two forms set passwords -- the users resource, where an administrator sets
 * somebody else's, and the profile page, where a reader sets their own -- and
 * a reader who was given a password in the panel must be able to type it back
 * into the profile form. So the rule and the generator live here rather than
 * in either form, and both read them from the same place.
 */
class AccountPassword
{
    /**
     * Long enough that the mix below is not the only thing carrying it.
     */
    public const int MINIMUM_LENGTH = 12;

    /**
     * Comfortably over the minimum: nobody has to remember a generated one.
     */
    public const int GENERATED_LENGTH = 16;

    public static function rule(): Password
    {
        return Password::min(self::MINIMUM_LENGTH)->letters()->mixedCase()->numbers();
    }

    /**
     * `Str::password()` guarantees length but not case mix, so a run of it can
     * lose to the `mixedCase()` rule roughly once in every few hundred calls.
     * Rerolling is cheaper than explaining that error to whoever clicked.
     */
    public static function generate(): string
    {
        do {
            $password = str()->password(self::GENERATED_LENGTH);
        } while (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}
