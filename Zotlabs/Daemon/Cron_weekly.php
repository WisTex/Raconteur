<?php

namespace Zotlabs\Daemon;

class Cron_weekly {

	static public function run($argc,$argv) {		

		/**
		 * Cron Weekly
		 * 
		 * Actions in the following block are executed once per day only on Sunday (once per week).
		 *
		 */

		call_hooks('cron_weekly',datetime_convert());

		z_check_cert();

		prune_hub_reinstalls();
	
		mark_orphan_hubsxchans();

		// Find channels that were removed in the last three weeks, but 
		// haven't been finally cleaned up. These should be older than 10
		// days to ensure that "purgeall" messages have gone out or bounced 
		// or timed out. 

		$r = q("select channel_id from channel where channel_removed = 1 and 
			channel_deleted >  %s - INTERVAL %s and channel_deleted < %s - INTERVAL %s",
			db_utcnow(), db_quoteinterval('21 DAY'),
			db_utcnow(), db_quoteinterval('10 DAY')
		);
		if($r) {
			foreach($r as $rv) {
				channel_remove_final($rv['channel_id']);
			}
		}

		// get rid of really old poco records

		q("delete from xlink where xlink_updated < %s - INTERVAL %s and xlink_static = 0 ",
			db_utcnow(), db_quoteinterval('14 DAY')
		);

		$dirmode = intval(get_config('system','directory_mode'));
		if($dirmode === DIRECTORY_MODE_SECONDARY || $dirmode === DIRECTORY_MODE_PRIMARY) {
			logger('regdir: ' . print_r(z_fetch_url(get_directory_primary() . '/regdir?f=&url=' . urlencode(z_root()) . '&realm=' . urlencode(get_directory_realm())),true));
		}

		// Check for dead sites
		Master::Summon(array('Checksites'));


		// clean up image cache - use site expiration or 180 days if not set
		
		$files = glob('cache/img/*/*');
		$expire_days = intval(get_config('system','default_expire_days', 180));
		$now = time();

		if ($files) {
			foreach ($files as $file) {
				if (is_file($file)) {
					if ($now - filemtime($file) >= 60 * 60 * 24 * $expire_days) {
						unlink($file);
					}
				}
			}
		}

		// update searchable doc indexes
		// disabled until help system regenerated
		// Master::Summon(array('Importdoc'));

		/**
		 * End Cron Weekly
		 */

	}
}