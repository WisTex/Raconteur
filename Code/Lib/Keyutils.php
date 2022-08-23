<?php

namespace Code\Lib;

use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;

/**
 * Keyutils
 * Convert RSA keys between various formats
 */
class Keyutils
{

    /**
     * @param string $m modulo
     * @param string $e exponent
     * @return string
     */
    public static function meToPem(string $m, string $e): string
    {

        $rsa = new RSA();
        $rsa->loadKey([
            'e' => new BigInteger($e, 256),
            'n' => new BigInteger($m, 256)
        ]);
        return $rsa->getPublicKey();
    }

    /**
     * @param string $key
     * @return string
     */
    public static function rsaToPem(string $key): string
    {

        $rsa = new RSA();
        $rsa->setPublicKey($key);

        return $rsa->getPublicKey(RSA::PUBLIC_FORMAT_PKCS8);
    }

    /**
     * @param string $key
     * @return string
     */
    public static function pemToRsa(string $key): string
    {

        $rsa = new RSA();
        $rsa->setPublicKey($key);

        return $rsa->getPublicKey(RSA::PUBLIC_FORMAT_PKCS1);
    }

    /**
     * @param string $key key
     * @param string $m reference modulo
     * @param string $e reference exponent
     */
    public static function pemToMe(string $key, string &$m, string &$e): void
    {

        $rsa = new RSA();
        $rsa->loadKey($key);
        $rsa->setPublicKey();

        $m = $rsa->modulus->toBytes();
        $e = $rsa->exponent->toBytes();
    }

    /**
     * @param string $pubkey
     * @return string
     */
    public static function salmonKey(string $pubkey): string
    {
        self::pemToMe($pubkey, $m, $e);
        return 'RSA' . '.' . base64url_encode($m, true) . '.' . base64url_encode($e, true);
    }

    /**
     * @param string $key
     * @return string
     */
    public static function convertSalmonKey(string $key): string
    {
        if (str_contains($key, ',')) {
            $rawkey = substr($key, strpos($key, ',') + 1);
        } else {
            $rawkey = substr($key, 5);
        }

        $key_info = explode('.', $rawkey);

        $m = base64url_decode($key_info[1]);
        $e = base64url_decode($key_info[2]);

        return self::meToPem($m, $e);
    }
}
