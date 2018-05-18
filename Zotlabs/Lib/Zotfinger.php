<?php

namespace Zotlabs\Lib;


class Zotfinger {


	static function exec($resource) {

		if(! $resource) {
			return false;
		}

		$result = [];

		$headers = 'Accept: application/x-zot+json';
		$redirects = 0;
		$x = z_fetch_url($resource,false,$redirects, [ 'headers' => [ $headers ]] );

		if($x['success']) {
			$result['signature'] = \Zotlabs\Web\HTTPSig::verify($x);    
			$result['data'] = json_decode($x['body'],true);
			return $result;
		}

		return false;
	}



}