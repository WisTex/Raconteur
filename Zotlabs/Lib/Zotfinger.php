<?php

namespace Zotlabs\Lib;


class Zotfinger {


	static function exec($resource,$channel = null) {

		if(! $resource) {
			return false;
		}


		$headers = [];
		$headers['Accept'] = 'application/x-zot+json'; 

		$h = $headers;

		if($channel) {
			$headers['X-Zot-Token'] = random_string();
			$h = \Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],channel_url($channel),false,false);
		}

logger('headers: ' . print_r($h,true));

		$result = [];

		$redirects = 0;
		$x = z_fetch_url($resource,false,$redirects, [ 'headers' => $h  ] );

		logger('zotfinger: ' . print_r($x,true));

		if($x['success']) {
			$result['signature'] = \Zotlabs\Web\HTTPSig::verify($x);    
			$result['data'] = json_decode($x['body'],true);

			if($result['data'] && is_array($result['data']) && array_key_exists('encrypted',$result['data']) && $result['data']['encrypted']) {
				$result['data'] = json_decode(crypto_unencapsulate($result['data'],get_config('system','prvkey')),true);
			}

logger('result: ' . print_r($result,true));

			return $result;
		}

		return false;
	}



}