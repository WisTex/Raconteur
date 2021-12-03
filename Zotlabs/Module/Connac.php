<?php
namespace Zotlabs\Module;

// Connection autocompleter for util/nsh
// We could probably add this case to the general purpose autocompleter (mod_acl) but
// that module has gotten far too overloaded.
// Returns as json a simply array containing the webfinger addresses of all your Nomad connections

use Zotlabs\Web\Controller;


class Connac extends Controller
{

    public function init()
    {

        $ret = [];

        if (!local_channel()) {
            json_return_and_die($ret);
        }


        $r = q("select xchan_addr from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d and xchan_network = 'zot6'",
            intval(local_channel())
        );
        if ($r) {
            foreach ($r as $rv) {
                $ret[] = $rv['xchan_addr'];
            }
        }
        json_return_and_die($ret);
    }
}
