<?php

namespace Zotlabs\Lib;


class Discover {

	private $resource      = null;
	private $resource_type = null;
	private $webfinger     = null;
	private $zotinfo       = null;

	function run($resource) {

		$this->resource  = $resource;
		$this->webfinger = webfinger_rfc7033($this->resource);

		if(is_array($this->webfinger) && array_key_exists('links',$this->webfinger)) {
			foreach($this->webfinger['links'] as $link) {
				if(array_key_exists('rel',$link) && $link['rel'] === PROTOCOL_ZOT6) {
					if(array_key_exists('href',$link) && $link['href'] !== EMPTY_STR) {
						$headers = 'Accept: application/x-zot+json';
						$redirects = 0;
						$this->zotinfo = z_fetch_url($link['href'],true,$redirects, 
							[ 'headers' => [ $headers ]]
						);
					}
				}
			}
		}

		return [ 
			'resource'      => $this->resource, 
			'resource_type' => $this->resource_type,
			'webfinger'     => $this->webfinger,
			'zotinfo'       => $this->zotinfo
		];

	}

}