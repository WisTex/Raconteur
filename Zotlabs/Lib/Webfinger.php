<?php

namespace Zotlabs\Lib;

/**
 * @brief Fetch and return a webfinger for a resource
 *
 * @param string $resource - The resource
 * @return boolean|string false or associative array from result JSON
 */

class Webfinger {

	static private $server   = EMPTY_STR;
	static private $resource = EMPTY_STR;

	static function exec($resource) {

		if(! $resource) {
			return false;
		}

		self::parse_resource($resource);

		if(! ( self::$server && self::$resource)) {
			return false;
		}

		logger('fetching resource from url: ' . self::$resource . ':' . self::$server, LOGGER_DEBUG, LOG_INFO);

		$counter = 0;
		$s = z_fetch_url('https://' . self::$server . '/.well-known/webfinger?f=&resource=' . self::$resource, false, $counter, [ 'headers' => [ 'Accept: application/jrd+json, */*' ] ]);

		if($s['success']) {
			$j = json_decode($s['body'], true);
			return($j);
		}

		return false;
	}

	static function parse_resource($resource) {

		self::$resource = urlencode($resource);

		if(strpos($resource,'http') === 0) {
			$m = parse_url($resource);
			if($m) {
				if($m['scheme'] !== 'https') {
					return false;
				}
				self::$server = $m['host'] . (($m['port']) ? ':' . $m['port'] : '');
			}
			else {
				return false;
			}
		}
		elseif(strpos($resource,'tag:') === 0) {
			$arr = explode(':',$resource); // split the tag
			$h = explode(',',$arr[1]); // split the host,date
			self::$server = $h[0];
		}
		else {
			$x = explode('@',$resource);
			$username = $x[0];
			if(count($x) > 1) {
				self::$server = $x[1];
			}
			else {
				return false;
			}
			if(strpos($resource,'acct:') !== 0) {
				self::$resource = urlencode('acct:' . $resource);
			}
		}

	}

	static function zot_url($arr) {

		if(is_array($arr) && array_key_exists('links',$arr)) {
			foreach($arr['links'] as $link) {
				if(array_key_exists('rel',$link) && $link['rel'] === PROTOCOL_ZOT6) {
					if(array_key_exists('href',$link) && $link['href'] !== EMPTY_STR) {
						return $link['href'];
					}
				}
			}
		}
		return false;
	}



}