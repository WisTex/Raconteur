<?php

namespace Code\Update;

use Code\Lib\Channel;
    
class _1252
{

    public function run()
    {
        $sys = Channel::get_system();
        if ($sys) {
            $sitename = get_config('system', 'sitename');
            $siteinfo = get_config('system', 'siteinfo');

            if ($sitename) {
                q(
                    "update channel set channel_name = '%s' where channel_id = %d",
                    dbesc($sitename),
                    intval($sys['channel_id'])
                );
                q(
                    "update profile set fullname = '%s' where uid = %d and is_default = 1",
                    dbesc($sitename),
                    intval($sys['channel_id'])
                );
                q(
                    "update xchan set xchan_name = '%s', xchan_name_date = '%s'  where xchan_hash = '%s'",
                    dbesc($sitename),
                    dbesc(datetime_convert()),
                    dbesc($sys['channel_hash'])
                );
            }
            if ($siteinfo) {
                q(
                    "update profile set about = '%s' where uid = %d and is_default = 1",
                    dbesc($siteinfo),
                    intval($sys['channel_id'])
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
