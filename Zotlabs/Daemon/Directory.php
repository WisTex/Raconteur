<?php

namespace Zotlabs\Daemon;


use Zotlabs\Lib\Libzot;;
use Zotlabs\Lib\Libzotdir;
use Zotlabs\Lib\Queue;



class Directory {

	static public function run($argc,$argv) {

		if($argc < 2)
			return;

		$force = false;
		$pushall = true;

		if($argc > 2) {
			if($argv[2] === 'force')
				$force = true;
			if($argv[2] === 'nopush')
				$pushall = false;
		}	

		logger('directory update', LOGGER_DEBUG);

		$dirmode = get_config('system','directory_mode');
		if($dirmode === false)
			$dirmode = DIRECTORY_MODE_NORMAL;


		$channel = channelx_by_n($argv[1]);
		if(! $channel)
			return;

		if($dirmode != DIRECTORY_MODE_NORMAL) {

			// this is an in-memory update and we don't need to send a network packet.

			Libzotdir::local_dir_update($argv[1],$force);

			q("update channel set channel_dirdate = '%s' where channel_id = %d",
				dbesc(datetime_convert()),
				intval($channel['channel_id'])
			);

			// Now update all the connections
			if($pushall) 
				Master::Summon(array('Notifier','refresh_all',$channel['channel_id']));

			return;
		}

		// otherwise send the changes upstream

		$directory = Libzotdir::find_upstream_directory($dirmode);

		if(! $directory) {
			logger('no directory');
			return;
		}

		$url = $directory['url'] . '/zot';

		// ensure the upstream directory is updated

		$packet = Libzot::build_packet($channel,(($force) ? 'force_refresh' : 'refresh'));
		$z = Libzot::zot($url,$packet,$channel);

		// re-queue if unsuccessful

		if(! $z['success']) {

			/** @FIXME we aren't updating channel_dirdate if we have to queue
			 * the directory packet. That means we'll try again on the next poll run.
			 */

			$hash = new_uuid();

			Queue::insert(array(
				'hash'       => $hash,
				'account_id' => $channel['channel_account_id'],
				'channel_id' => $channel['channel_id'],
				'posturl'    => $url,
				'notify'     => $packet,
			));

		}
		else {
			q("update channel set channel_dirdate = '%s' where channel_id = %d",
				dbesc(datetime_convert()),
				intval($channel['channel_id'])
			);
		}

		// Now update all the connections
		if($pushall)
			Master::Summon(array('Notifier','refresh_all',$channel['channel_id']));

	}
}
