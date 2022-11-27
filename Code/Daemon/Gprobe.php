<?php

/** @file */

namespace Code\Daemon;

use Code\Lib\Libzot;
use Code\Lib\Webfinger;
use Code\Lib\Zotfinger;

// performs zot_finger on $argv[1], which is a hex_encoded webbie/reddress

class Gprobe implements DaemonInterface
{

    public function run(int $argc, array $argv): void
    {

        if ($argc != 2) {
            return;
        }

        $address = $argv[1];
        $protocols = [];

        if (! strpos($address, '@')) {
            return;
        }

        $r = q(
            "select * from hubloc where hubloc_addr = '%s' and hubloc_deleted = 0",
            dbesc($address)
        );

        if ($r) {
            foreach ($r as $rv) {
                if ($rv['hubloc_network'] === 'activitypub') {
                    $protocols[] = 'activitypub';
                    continue;
                }
				if ($rv['hubloc_network'] === 'nomad') {
					$protocols[] = 'nomad';
					$protocols[] = 'zot6';
					continue;
				}
                if ($rv['hubloc_network'] === 'zot6') {
                    $protocols[] = 'zot6';
                    continue;
                }
            }
        }

        if ((!in_array('zot6', $protocols)) && (!in_array('nomad', $protocols))) {
            $href = Webfinger::nomad_url(punify($address));
            if ($href) {
                $zf = Zotfinger::exec($href, $channel);
            }
            if (is_array($zf) && array_path_exists('signature/signer', $zf) && $zf['signature']['signer'] === $href && intval($zf['signature']['header_valid']) && isset($zf['data']) && $zf['data']) {
                $xc = Libzot::import_xchan($zf['data']);
            }
        }

        return;
    }
}
