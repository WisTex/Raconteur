<?php

namespace Zotlabs\Daemon;


class Channel_purge {

	static public function run($argc,$argv) {

		cli_startup();

		$channel_id = intval($argv[1]);

		$channel = q("select * from channel where channel_id = %d and channel_removed = 1"
			intval($channel_id)
		);

		if (! $channel) {
			return;
		}

		do {
			$r = q("select id from item where uid = %d and item_deleted = 0 limit 1000",
				intval($channel_id)
			);
			if ($r) {
				foreach ($r as $rv) {
					drop_item($rv['id'],false);
				}
			}
		} while ($r);
	}
}
