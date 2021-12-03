<?php

namespace Zotlabs\Update;

use Zotlabs\Lib\Config;

class _1253
{

    public function run()
    {
        $mode = PUBLIC_STREAM_NONE;
        if (Config::Get('system', 'site_firehose')) {
            $mode = PUBLIC_STREAM_SITE;
        }
        if (intval(Config::Get('system', 'disable_discover_tab', 1)) === 0) {
            $mode = PUBLIC_STREAM_FULL;
        }
        Config::Set('system', 'public_stream_mode', $mode);
        Config::Delete('system', 'disable_discover_tab');
        Config::Delete('system', 'site_firehose');

        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}
