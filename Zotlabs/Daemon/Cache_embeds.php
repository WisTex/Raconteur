<?php /** @file */

namespace Zotlabs\Daemon;


class Cache_embeds {

	static public function run($argc,$argv) {

		if(! $argc == 2)
			return;

		$c = q("select body from item where id = %d ",
			dbesc(intval($argv[1]))
		);

		if(! $c)
			return;

		$item = $c[0];

		// bbcode conversion by default processes embeds that aren't already cached.
		// Ignore the returned html output. 

		bbcode($item['body']);
	}
}
