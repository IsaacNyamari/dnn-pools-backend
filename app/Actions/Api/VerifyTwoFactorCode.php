<?php

namespace App\Actions\Api;

use App\Exceptions\ApiException;
use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class VerifyTwoFactorCode
{
    public function __construct(private TwoFactorAuthenticationProvider $provider) {}

    /**
     * Check a TOTP code or a recovery code. A TOTP code is used up by this check. A
     * recovery code is returned so the caller can consume it once the sign-in succeeds.
     */
    public function handle(User $user, string $code): ?string
    {
        $recoveryCode = collect($user->recoveryCodes())
            ->first(fn (string $candidate) => hash_equals($candidate, $code));

        if ($recoveryCode !== null) {
            return $recoveryCode;
        }

        $totp = (string) preg_replace('/\s+/', '', $code);

        if (preg_match('/^\d{6}$/D', $totp) === 1 && $this->provider->verify(
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            $totp,
        )) {
            return null;
        }

        throw ApiException::twoFactorInvalid();
    }
}
