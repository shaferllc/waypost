<?php

namespace App\Services;

use App\Models\User;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA,
    ) {}

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );
    }

    public function qrCodeSvg(string $otpauthUrl): string
    {
        $options = new QROptions([
            'outputBase64' => false,
            'imageTransparent' => false,
        ]);

        return (string) (new QRCode($options))->render($otpauthUrl);
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;
        if (! is_string($secret) || $secret === '') {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * @return bool True if a recovery code was consumed.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));
        $hashes = $user->two_factor_recovery_codes ?? [];

        if (! is_array($hashes) || $hashes === []) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (! is_string($hash)) {
                continue;
            }

            if (Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => array_values($hashes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> Plain recovery codes (show once to the user).
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $plainCodes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(fn (string $c): string => Hash::make(Str::upper(trim($c))), $plainCodes);
    }
}
