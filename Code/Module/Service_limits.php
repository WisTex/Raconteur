<?php

namespace Code\Module;

use App;
use Code\Web\Controller;

/** @file */
class Service_limits extends Controller
{

    public function get()
    {

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $account = App::get_account();
        if ($account['account_service_class']) {
            $x = get_config('service_class', $account['account_service_class']);
            if ($x) {
                $o = print_r($x, true);
                return $o;
            }
        }
        return t('No service class restrictions found.');
    }
}
