<?php

namespace App\Services;

use App\Models\User;

class AdminTwoFactorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Generate a Base32 secret suitable for TOTP apps.
     */
    public function generateSecret(int $length = 32): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    /**
     * Build an otpauth URI to support authenticator app enrollment.
     */
    public function getOtpAuthUrl(User $user, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'U9itus'));
        $label = rawurlencode((string) config('app.name', 'U9itus') . ':' . $user->email);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            $secret,
            $issuer
        );
    }

    /**
     * Verify a submitted TOTP code within a small drift window.
     */
    public function verifyCode(string $secret, string $code, ?int $window = null): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if (strlen($normalizedCode) !== 6) {
            return false;
        }

        $window = $window ?? (int) config('platform.standalone.auth.admin_2fa.totp_window', 1);
        $counter = intdiv(time(), 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $expected = $this->generateTotpCode($secret, $counter + $offset);
            if (hash_equals($expected, $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate one-time recovery codes shown once after enrollment/rotation.
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $chunk = '';
            for ($j = 0; $j < 8; $j++) {
                $chunk .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
            }

            $codes[] = substr($chunk, 0, 4) . '-' . substr($chunk, 4, 4);
        }

        return $codes;
    }

    /**
     * Attempt to consume a recovery code.
     *
     * @param  array<int, string>  $storedCodes
     * @return array<int, string>|null Remaining codes when consumed, or null when invalid.
     */
    public function consumeRecoveryCode(array $storedCodes, string $submittedCode): ?array
    {
        $submitted = $this->normalizeRecoveryCode($submittedCode);

        foreach ($storedCodes as $index => $storedCode) {
            if (hash_equals($this->normalizeRecoveryCode((string) $storedCode), $submitted)) {
                unset($storedCodes[$index]);
                return array_values($storedCodes);
            }
        }

        return null;
    }

    private function generateTotpCode(string $secret, int $counter): string
    {
        $decodedSecret = $this->decodeBase32($secret);

        if ($decodedSecret === '') {
            return '000000';
        }

        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $decodedSecret, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = $truncated % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');

        if ($value === '') {
            return '';
        }

        $binary = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            $index = strpos(self::BASE32_ALPHABET, $char);

            if ($index === false) {
                return '';
            }

            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
            $decoded .= chr(bindec(substr($binary, $i, 8)));
        }

        return $decoded;
    }

    private function normalizeRecoveryCode(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $value) ?? '');
    }
}
