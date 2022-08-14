<?php

require_once('boot.php');

// Everything we need to boot standalone 'background' processes

function cli_startup(): void
{
    sys_boot();
    App::set_baseurl(get_config('system', 'baseurl'));
}
