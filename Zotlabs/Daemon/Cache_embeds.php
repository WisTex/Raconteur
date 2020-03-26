<?php

namespace Zotlabs\Daemon;


class Cache_embeds {

	static public function run($argc,$argv) {

		if (! $argc == 2) {
			return;
		}

		$c = q("select body, html from item where id = %d ",
			dbesc(intval($argv[1]))
		);

		if (! $c) {
			return;
		}

		$item = array_shift($c);

		// bbcode conversion by default processes embeds that aren't already cached.

		if (! strlen($item['html'])) {
			$s = bbcode($item['body']);
			$s = sslify($s);
		}
	}
}
