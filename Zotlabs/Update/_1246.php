<?php

namespace Zotlabs\Update;

class _1246
{

    public function run()
    {
        // remove deprecated apps from system list

        $network = 'bee400a93e3b95225374f02dcc74bfa37445f628bb10d05e3904aa8e3dd12c3b337a0caed2455a32ea85d8aaebc310f5b8571e7471f10a19bcfd77a7a997681f';
        $affinity = '76eecd5b73a0df8cddfcda430f0970ce82fefddb2b6440397b8318ccf4ae8011953fe249b0e450a1fe8829d5d3b5a62588818fd859087bbddc65a99c856d8b7d';


        q("delete from app where (app_id = '$network' OR app_id = '$affinity')");
        return UPDATE_SUCCESS;

    }

    public function verify()
    {
        return true;
    }

}
