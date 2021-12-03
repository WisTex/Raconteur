<?php /** @file */

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Webfinger;
use Zotlabs\Lib\Zotfinger;

// performs zot_finger on $argv[1], which is a hex_encoded webbie/reddress

class Gprobe {

	public static function run($argc, $argv) {


		if ($argc != 2) {
			return;
		}

		$url = hex2bin($argv[1]);
		$protocols = [];
		
		if (! strpos($url,'@')) {
			return;
		}

		$r = q("select * from hubloc where hubloc_addr = '%s'",
			dbesc($url)
		);

		if ($r) {
			foreach ($r as $rv) {
				if ($rv['hubloc_network'] === 'activitypub') {
					$protocols[] = 'activitypub';
					continue;
				}
				if ($rv['hubloc_network'] === 'zot6') {
					$protocols[] = 'zot6';
					continue;
				}
			}
		}

		if (! in_array('zot6',$protocols)) {
			$href = Webfinger::zot_url(punify($url));
            if ($href) {
                $zf = Zotfinger::exec($href,$channel);
            }
			if (is_array($zf) && array_path_exists('signature/signer',$zf) && $zf['signature']['signer'] === $href && intval($zf['signature']['header_valid']) && isset($zf['data']) && $zf['data']) {
                $xc = Libzot::import_xchan($zf['data']);
			}
		}

		return;
	}
}
