<?php

namespace Zotlabs\Lib;

use Zotlabs\Web\HTTPSig;

class Zotfinger {

	static function exec($resource,$channel = null) {

		if(! $resource) {
			return false;
		}

		$m = parse_url($resource);

		$data = json_encode([ 'zot_token' => random_string() ]);

		if($channel && $m) {

			$headers = [ 
				'Accept'           => 'application/x-zot+json', 
				'Content-Type'     => 'application/x-zot+json',
				'X-Zot-Token'      => random_string(),
				'Digest'           => HTTPSig::generate_digest_header($data),
				'Host'             => $m['host'],
				'(request-target)' => 'post ' . get_request_string($resource)
			];
			$h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel),false);
		}
		else {
			$h = [ 'Accept: application/x-zot+json' ]; 
		}
				
		$result = [];


		$redirects = 0;
		$x = z_post_url($resource,$data,$redirects, [ 'headers' => $h  ] );

		if($x['success']) {
			
			$result['signature'] = HTTPSig::verify($x);
    
			$result['data'] = json_decode($x['body'],true);

			if($result['data'] && is_array($result['data']) && array_key_exists('encrypted',$result['data']) && $result['data']['encrypted']) {
				$result['data'] = json_decode(crypto_unencapsulate($result['data'],get_config('system','prvkey')),true);
			}

			return $result;
		}

		return false;
	}



}