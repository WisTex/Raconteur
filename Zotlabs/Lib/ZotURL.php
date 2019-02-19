<?php

namespace Zotlabs\Lib;

use Zotlabs\Web\HTTPSig;


class ZotURL {

	static public function fetch($url,$channel) {

		$ret = [ 'success' => false ];

		if(strpos($url,'x-zot:') !== 0) {
			return $ret;
		}


		if(! $url) {
			return $ret;
		}

		$portable_url = substr($url,6);
		$u = explode('/',$portable_url);		
		$portable_id = $u[0];

		$hosts = self::lookup($portable_id);

		if(! $hosts) {
			return $ret;
		}

		foreach($hosts as $h) {
			$newurl = $h . '/id/' . $portable_url;

			$m = parse_url($newurl);

			$data = json_encode([ 'zot_token' => random_string() ]);

			if($channel && $m) {

				$headers = [ 
					'Accept'           => 'application/x-zot+json', 
					'Content-Type'     => 'application/x-zot+json',
					'X-Zot-Token'      => random_string(),
					'Digest'           => HTTPSig::generate_digest_header($data),
					'Host'             => $m['host'],
					'(request-target)' => 'post ' . get_request_string($newurl)
				];
				$h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel),false);
			}
			else {
				$h = [ 'Accept: application/x-zot+json' ]; 
			}
				
			$result = [];

			$redirects = 0;
			$x = z_post_url($newurl,$data,$redirects, [ 'headers' => $h  ] );
			if($x['success']) {
				return $x;
			}
		}

		return $ret;

	}

	static public function lookup($portable_id) {

		$r = q("select * from hubloc left join site on hubloc_url = site_url where hubloc_hash = '%s' and site_dead = 0 order by hubloc_primary desc",
			dbesc($portable_id)
		);

		if(! $r) {
			// extend to network lookup
			return false;
		}
		return ids_to_array($r,'hubloc_url');
	}

}