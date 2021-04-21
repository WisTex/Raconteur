<?php

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Img_cache;

class Cache_image {

	static public function run($argc,$argv) {

		cli_startup();

		if ($argc === 3) {
			Img_cache::url_to_cache($argv[1],$argv[2]);
		}

	}
}
