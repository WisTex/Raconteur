<?php

namespace Zotlabs\Update;

use Zotlabs\Lib\Apps;

class _1225
{

    public function run()
    {
        q("delete from app where app_channel = 0");

        $apps = Apps::get_system_apps(false);

        if ($apps) {
            foreach ($apps as $app) {
                $app['uid'] = 0;
                $app['guid'] = hash('whirlpool', $app['name']);
                $app['system'] = 1;
                Apps::app_install(0, $app);
            }
        }

        return UPDATE_SUCCESS;
    }
}
