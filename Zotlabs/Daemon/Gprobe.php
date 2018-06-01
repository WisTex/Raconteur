<?php /** @file */

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Libzot;

// performs zot_finger on $argv[1], which is a hex_encoded webbie/reddress

class Gprobe {
	static public function run($argc,$argv) {

		if($argc != 2)
			return;

		$url = hex2bin($argv[1]);

		if(! strpos($url,'@'))
			return;

		$r = q("select * from hubloc where hubloc_addr = '%s' limit 1",
			dbesc($url)
		);

		if(! $r) {
			$href = \Zotlabs\Lib\Webfinger::zot_url(punify($url));
            if($href) {
                $zf = \Zotlabs\Lib\Zotfinger::exec($href,$channel);
            }
			if(is_array($zf) && array_path_exists('signature/signer',$zf) && $zf['signature']['signer'] === $href && intval($zf['signature']['header_valid'])) {
                $xc = import_xchan($zf['data']);
			}
		}

		return;
	}
}
