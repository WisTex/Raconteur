<?php

namespace Zotlabs\Lib;

use Zotlabs\Web\HTTPSig;

class JSalmon {

	static function sign($data,$key_id,$key) {

		$arr       = $data;
		$data      = json_encode($data,JSON_UNESCAPED_SLASHES);
		$data      = base64url_encode($data, false); // do not strip padding
		$data_type = 'application/x-zot+json';
		$encoding  = 'base64url';
		$algorithm = 'RSA-SHA256';

		$data = preg_replace('/\s+/','',$data);

		// precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

		$precomputed = '.' . base64url_encode($data_type,false) . '.YmFzZTY0dXJs.UlNBLVNIQTI1Ng==';

		$signature  = base64url_encode(rsa_sign($data . $precomputed, $key), false);

		return ([
			'signed'    => true,
			'data'      => $data,
			'data_type' => $data_type,
			'encoding'  => $encoding,
			'alg'       => $algorithm,
			'sigs'      => [
				'value'  => $signature,
				'key_id' => base64url_encode($key_id)
			]
		]);

	}

	static function verify($x) {

		$ret = [ 'results' => [] ];

		if(! is_array($x)) {
			return $false;
		}
		if(! ( array_key_exists('signed',$x) && $x['signed'])) {
			return $false;
		}

		$signed_data = preg_replace('/\s+/','',$x) . '.' . base64url_encode($x['data_type'],false) . '.' . base64url_encode($x['encoding'],false) . '.' . base64url_encode($x['alg'],false);

		foreach($sigs as $sig) {		
			$key = HTTPSig::get_key(EMPTY_STR,base64url_decode($x['sig']['key_id']));
			if($key['portable_id'] && $key['public_key']) {
				if(rsa_verify($signed_data,base64url_decode($x['sigs']['value']),$key['public_key'])) {
					$ret['results'][] = [ 'success' => true, 'signer' => $key['portable_id'] ];
				}
			}
		}

		return $ret;

	}

	static function unpack($data) {
		return json_decode(base64url_decode($data),true);
	}


}