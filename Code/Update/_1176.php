<?php

namespace Code\Update;

use Code\Lib\IConfig;

class _1176
{
    public function run()
    {

        $r = q("select * from item_id where true");
        if ($r) {
            foreach ($r as $rr) {
                IConfig::Set($rr['iid'], 'system', $rr['service'], $rr['sid'], true);
            }
        }
        return UPDATE_SUCCESS;
    }
}
