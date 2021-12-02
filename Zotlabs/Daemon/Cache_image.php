<?php

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Img_cache;

class Cache_image {

	public static function run($argc, $argv) {

		cli_startup();
		logger('caching: ' . $argv[1] . ' to ' . $argv[2]);
		if ($argc === 3) {
			Img_cache::url_to_cache($argv[1],$argv[2]);
		}

	}
}
