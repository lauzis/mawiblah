<?php

namespace Mawiblah;

/**
 * Keeps a setting encrypted at rest.
 *
 * The key comes from the site's salts in wp-config.php, so a database dump or a
 * backup of wp_options on its own does not give the value away. It is no defence
 * against someone who can also read wp-config.php -- they can run the site.
 *
 * Changing the salts leaves stored values unreadable: decrypt() then answers ''
 * and the value has to be entered again.
 */
class Secrets
{
    private const PREFIX = 'mawiblah-secret:v1:';

    /** True when the value is already in the stored, encrypted form. */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Encrypts a value for storage. An empty or already encrypted value is
     * returned as it is, so saving a form that re-posts the stored value does
     * not wrap it a second time.
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '' || self::isEncrypted($plain)) {
            return $plain;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    /**
     * Decrypts a stored value. A value saved before it was encrypted is
     * returned unchanged; one that cannot be decrypted comes back as ''.
     */
    public static function decrypt(string $stored): string
    {
        if (!self::isEncrypted($stored)) {
            return $stored;
        }

        $decoded = base64_decode(substr($stored, strlen(self::PREFIX)), true);

        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce  = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, self::key());

        return $plain === false ? '' : $plain;
    }

    /** The encryption key, derived from the site's secure-auth salt. */
    private static function key(): string
    {
        if (function_exists('wp_salt')) {
            $material = wp_salt('secure_auth');
        } else {
            $material = (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '')
                . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '');
        }

        return sodium_crypto_generichash('mawiblah-secrets|' . $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
