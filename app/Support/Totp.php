<?php

namespace App\Support;

/**
 * Códigos de un solo uso por tiempo (RFC 6238), compatibles con Google Authenticator,
 * Microsoft Authenticator, Authy, etc.: 6 dígitos que cambian cada 30 segundos.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * ¿El código es válido? Acepta un intervalo antes y uno después (relojes desfasados).
     * Devuelve el intervalo usado (para no aceptar dos veces el mismo código) o null.
     */
    public static function verify(string $secret, string $code, ?int $lastUsedStep = null, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $step = intdiv($time ?? now()->timestamp, 30);
        foreach ([0, -1, 1] as $offset) {
            $candidate = $step + $offset;
            if ($lastUsedStep !== null && $candidate <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $candidate), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Enlace otpauth:// que se muestra como QR para agregar la cuenta en la app.
     */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    private static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn ($chunk) => self::ALPHABET[bindec(str_pad($chunk, 5, '0'))], str_split($bits, 5)));
    }

    private static function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        return implode('', array_map(fn ($byte) => chr(bindec($byte)), array_filter(str_split($bits, 8), fn ($b) => strlen($b) === 8)));
    }
}
