<?php

namespace Code\Module;

// Mastodon compatible "export follows"


use Code\Lib\Activity;
use Code\Web\Controller;
use Code\Lib\AbConfig;

class Export_follows extends Controller
{
    public function init()
    {

        if (! local_channel()) {
            return;
        }
dbg(2);
        $table = 'Account address,Show boosts' . "\n";
        $connections = q("select abook_xchan from abook 
                left join abconfig on abconfig.xchan = abook_xchan and abook_channel = abconfig.chan
                where abook_channel = %d and abconfig.cat = 'system' and abconfig.k = 'my_perms' and abconfig.v like '%%send_stream%%'
                and abook_hidden = 0 and abook_pending = 0 and abook_self = 0",
            intval(local_channel())
        );
dbg(0);
        if ($connections) {
            $str = ids_to_querystr($connections, 'abook_xchan',true);
            $locations = q("select hubloc_hash, hubloc_addr from hubloc where hubloc_hash in ($str) and hubloc_deleted = 0");
            if ($locations) {
                foreach ($locations as $location) {
                    $table .= str_contains($location['hubloc_addr'],',') ? '"' . $location['hubloc_addr'] . '"' : $location['hubloc_addr'];
                    $table .= ',';
                    $table .= AbConfig::Get(local_channel(), $location['hubloc_hash'], 'system', 'block_announce') ? 'false' : 'true';
                    $table .= "\n";
                }
            }
        }
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="following_accounts.csv"');
        echo $table;
        killme();
    }

    public function get()
    {
        if (!local_channel()) {
            return login();
        }
    }
}
