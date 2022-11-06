<?php

namespace Code\Update;

use Code\Lib\ObjCache;

class _1263
{

    public function run()
    {
        while (1) {
            $results = q("select iconfig.*, item.mid from iconfig left join item on iid = item.id
                where cat = 'activitypub' and k = 'rawmsg' limit 300");
            if (!$results) {
                break;
            }
            foreach ($results as $result) {
                if ($result['mid']) {
                    ObjCache::Set($result['mid'],$result['v']);
                }
                q("delete from iconfig where id = %d",
                    intval($result['id'])
                );

            }
        }
        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}
