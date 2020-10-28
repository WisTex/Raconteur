<?php

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Libzot;

/*

This file is being kept for archival reference at this time.
It is not currently referenced elsewhere. Its purpose is to grab
some public posts from random zot sites by polling occasionally
and asking for a 'zotfeed'. This is generally stored in the
'public stream' which is owned by the system channel on this site.
If somebody wishes to bring back this functionality, it should be
modified to read public outboxes in ActivityStreams format.

*/






class Externals {

	static public function run($argc, $argv){

		$total = 0;
		$attempts = 0;

		$sys = get_sys_channel();

		logger('externals: startup', LOGGER_DEBUG);

		// pull in some public posts

		while ($total == 0 && $attempts < 3) {
			$arr = [ 'url' => EMPTY_STR ];
			call_hooks('externals_url_select',$arr);

			if ($arr['url']) {
				$url = $arr['url'];
			} 
			else {
				$randfunc = db_getfunc('RAND');

				// fixme this query does not deal with directory realms. 

				$r = q("select site_url, site_pull from site where site_url != '%s' and site_flags != %d and site_type = %d and site_dead = 0 order by $randfunc limit 1",
					dbesc(z_root()),
					intval(DIRECTORY_MODE_STANDALONE),
					intval(SITE_TYPE_ZOT)
				);
				if ($r) {
					$url = $r[0]['site_url'];
				}
			}

			$denied = false;

			if (! check_siteallowed($url)) {
				logger('denied site: ' . $url);
				$denied = true;
			}

			$attempts ++;

			// make sure we can eventually break out if somebody blacklists all known sites

			if ($denied) {
				if ($attempts > 20) {
					break;
				}
				$attempts --;
				continue;
			}

			if ($url) {
				if ($r[0]['site_pull'] > NULL_DATE) {
					$mindate = urlencode(datetime_convert('','',$r[0]['site_pull'] . ' - 1 day'));
				}
				else {
					$days = get_config('externals','since_days',15);
					$mindate = urlencode(datetime_convert('','','now - ' . intval($days) . ' days'));
				}

				$feedurl = $url . '/zotfeed?f=&mindate=' . $mindate;

				logger('externals: pulling public content from ' . $feedurl, LOGGER_DEBUG);

				$x = z_fetch_url($feedurl);
				if (($x) && ($x['success'])) {

					q("update site set site_pull = '%s' where site_url = '%s'",
						dbesc(datetime_convert()),
						dbesc($url)
					);

					$j = json_decode($x['body'],true);
					if($j['success'] && $j['messages']) {
						foreach($j['messages'] as $message) {
							// on these posts, clear any route info. 
							$message['route'] = EMPTY_STR;
							$results = Libzot::process_delivery('undefined', null, get_item_elements($message), [ $sys['xchan_hash'] ], false, true);
							$total ++;
						}
						logger('externals: import_public_posts: ' . $total . ' messages imported', LOGGER_DEBUG);
					}
				}
			}
		}
	}
}
