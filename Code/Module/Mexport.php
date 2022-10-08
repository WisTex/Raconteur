<?php

namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\AbConfig;

class Mexport extends Controller
{
    public function init() {
        if (! local_channel()) {
            return;
        }
        $table = 'Account address,Show boosts' . "\n";
        $connections = q("select * from abook where abook_channel = %d",
            intval(local_channel())
        );

        if ($connections) {
            $str = ids_to_querystr($connections, 'abook_chan');
            $locations = q("select hubloc_hash, hubloc_id_url from hubloc where hubloc_hash in ($str) and hubloc_deleted = 0");
            if ($locations) {
                foreach ($locations as $location) {
                    $table .= $location['hubloc_id_url'] . ','
                    . (AbConfig::Get(local_channel(), $location['hubloc_hash'], 'system', 'block_announce')) ? 'false' : 'true'
                        . "\n";
                }
            }
        }
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="following_accounts.csv"');
        echo $table;
        killme();
    }
    public function get() {
        if (!local_channel()) {
            return login();
        }
    }
}
