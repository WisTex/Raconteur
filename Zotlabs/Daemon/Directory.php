<?php

namespace Zotlabs\Daemon;


use Zotlabs\Lib\Libzot;;
use Zotlabs\Lib\Libzotdir;
use Zotlabs\Lib\Queue;



class Directory {

	static public function run($argc,$argv) {

		if ($argc < 2) {
			return;
		}

		$force = false;
		$pushall = true;
		
		if ($argc > 2) {
			if ($argv[2] === 'force') {
				$force = true;
			}
			if ($argv[2] === 'nopush') {
				$pushall = false;
			}
		}	

		logger('directory update', LOGGER_DEBUG);

		$channel = channelx_by_n($argv[1]);
		if (! $channel) {
			return;
		}

		// update the local directory - was optional, but now done regardless
		
		Libzotdir::local_dir_update($argv[1],$force);

		q("update channel set channel_dirdate = '%s' where channel_id = %d",
			dbesc(datetime_convert()),
			intval($channel['channel_id'])
		);

		// Now update all the connections
		if ($pushall) {
			Run::Summon( [ 'Notifier','refresh_all',$channel['channel_id'] ] );
		}
	}
}
