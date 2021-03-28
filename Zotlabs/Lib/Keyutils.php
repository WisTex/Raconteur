<?php

namespace Zotlabs\Lib;

use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;

/**
 * Keyutils
 * Convert RSA keys between various formats
 */
class Keyutils {

	/**
	 * @param string $m modulo
	 * @param string $e exponent
	 * @return string
	 */
	public static function meToPem($m, $e) {

		$rsa = new RSA();
		$rsa->loadKey([
			'e' => new BigInteger($e, 256),
			'n' => new BigInteger($m, 256)
		]);
		return $rsa->getPublicKey();

	}

	/**
	 * @param string key
	 * @return string
	 */
	public static function rsaToPem($key) {

		$rsa = new RSA();
		$rsa->setPublicKey($key);

		return $rsa->getPublicKey(RSA::PUBLIC_FORMAT_PKCS8);

	}

	/**
	 * @param string key
	 * @return string
	 */
	public static function pemToRsa($key) {

		$rsa = new RSA();
		$rsa->setPublicKey($key);

		return $rsa->getPublicKey(RSA::PUBLIC_FORMAT_PKCS1);

	}

	/**
	 * @param string $key key
	 * @param string $m reference modulo
	 * @param string $e reference exponent
	 */
	public static function pemToMe($key, &$m, &$e) {

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
	public static function salmonKey($pubkey) {
		self::pemToMe($pubkey, $m, $e);
		return 'RSA' . '.' . base64url_encode($m, true) . '.' . base64url_encode($e, true);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public static function convertSalmonKey($key) {
		if (strstr($key, ','))
			$rawkey = substr($key, strpos($key, ',') + 1);
		else
			$rawkey = substr($key, 5);

		$key_info = explode('.', $rawkey);

		$m = base64url_decode($key_info[1]);
		$e = base64url_decode($key_info[2]);

		return self::meToPem($m, $e);
	}

}