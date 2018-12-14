<?php

namespace Zotlabs\Lib;
use Exception;

class Crypto {


	static public function new_keypair($bits) {

		$openssl_options = [
			'digest_alg'       => 'sha1',
			'private_key_bits' => $bits,
			'encrypt_key'      => false 
		];

		$conf = get_config('system','openssl_conf_file');
	
		if ($conf) {
			$openssl_options['config'] = $conf;
		}

		$result = openssl_pkey_new($openssl_options);

		if (empty($result)) {
			logger('new_keypair: failed');
			return false;
		}

		// Get private key

		$response = [ 'prvkey' => '', 'pubkey' => '' ];

		openssl_pkey_export($result, $response['prvkey']);

		// Get public key
		$pkey = openssl_pkey_get_details($result);
		$response['pubkey'] = $pkey["key"];

		return $response;

	}


	static public function sign($data,$key,$alg = 'sha256') {

		if (! $key) {
			return false;
		}

		$sig = '';
		openssl_sign($data,$sig,$key,$alg);
		return $sig;
	}


	static public function verify($data,$sig,$key,$alg = 'sha256') {

		if (! $key) {
			return false;
		}

		try {
			$verify = openssl_verify($data,$sig,$key,$alg);
		}
		catch (Exception $e) {
			$verify = (-1);
		}

		if ($verify === (-1)) {
			while ($msg = openssl_error_string()) {
				logger('openssl_verify: ' . $msg,LOGGER_NORMAL,LOG_ERR);
			}
			btlogger('openssl_verify: key: ' . $key, LOGGER_DEBUG, LOG_ERR); 
		}

		return (($verify > 0) ? true : false);
	}

}
