<?php

namespace Zotlabs\Lib;



class Nodeinfo {

	static public function fetch($url) {
		$href = EMPTY_STR;
		$m = parse_url($url);
		if ($m['scheme'] && $m['host']) {
			$s = $m['scheme'] . '://' . $m['host'] . '/.well-known/nodeinfo';
			$n = z_fetch_url($s);
			if ($n['success']) {
				$j = json_decode($n['body'], true);
				if ($j && $j['links']) {
					foreach ($j['links'] as $l) {
						if ($l['rel'] === 'http://nodeinfo.diaspora.software/ns/schema/2.0' && $l['href']) {
							$href = $l['href'];
							
						}
					}
				}
			}
		}
		if ($href) {
			$n = z_fetch_url($href);
			if ($n['success']) {
				return json_decode($n['body'],true);
			}
		}
		return [];

	}

}