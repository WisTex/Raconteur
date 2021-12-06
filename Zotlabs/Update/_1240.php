<?php

namespace Zotlabs\Update;

use Zotlabs\Lib\Config;

class _1240
{

    public function run()
    {

        Config::Set('system', 'allowed_sites', Config::Get('system', 'whitelisted_sites', ''));
        Config::Delete('system', 'whitelisted_sites');
        Config::Set('system', 'denied_sites', Config::Get('system', 'blacklisted_sites', ''));
        Config::Delete('system', 'blacklisted_sites');

        Config::Set('system', 'pubstream_allowed_sites', Config::Get('system', 'pubstream_whitelisted_sites', ''));
        Config::Delete('system', 'pubstream_whitelisted_sites');
        Config::Set('system', 'pubstream_denied_sites', Config::Get('system', 'pubstream_blacklisted_sites', ''));
        Config::Delete('system', 'pubstream_blacklisted_sites');


        Config::Set('system', 'allowed_sites', Config::Get('system', 'whitelisted_sites', ''));
        Config::Delete('system', 'whitelisted_sites');
        Config::Set('system', 'denied_sites', Config::Get('system', 'blacklisted_sites', ''));
        Config::Delete('system', 'blacklisted_sites');

        Config::Set('system', 'pubstream_allowed_sites', Config::Get('system', 'pubstream_whitelisted_sites', ''));
        Config::Delete('system', 'pubstream_whitelisted_sites');
        Config::Set('system', 'pubstream_denied_sites', Config::Get('system', 'pubstream_blacklisted_sites', ''));
        Config::Delete('system', 'pubstream_blacklisted_sites');
        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        if (Config::Get('system', 'blacklisted_sites') !== null) {
            return false;
        }
        return true;
    }
}
