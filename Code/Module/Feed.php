<?php

namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\Channel;

require_once('include/feedutils.php');

class Feed extends Controller
{

    public function init()
    {

        $params = [];

        $params['begin'] = ((x($_REQUEST, 'date_begin')) ? $_REQUEST['date_begin'] : NULL_DATE);
        $params['end'] = ((x($_REQUEST, 'date_end')) ? $_REQUEST['date_end'] : '');
        $params['type'] = ((stristr(argv(0), 'json')) ? 'json' : 'xml');
        $params['pages'] = ((x($_REQUEST, 'pages')) ? intval($_REQUEST['pages']) : 0);
        $params['top'] = ((x($_REQUEST, 'top')) ? intval($_REQUEST['top']) : 0);
        $params['start'] = ((x($_REQUEST, 'start')) ? intval($_REQUEST['start']) : 0);
        $params['records'] = ((x($_REQUEST, 'records')) ? intval($_REQUEST['records']) : 40);
        $params['direction'] = ((x($_REQUEST, 'direction')) ? dbesc($_REQUEST['direction']) : 'desc');
        $params['cat'] = ((x($_REQUEST, 'cat')) ? escape_tags($_REQUEST['cat']) : '');
        $params['compat'] = ((x($_REQUEST, 'compat')) ? intval($_REQUEST['compat']) : 0);

        if (!in_array($params['direction'], ['asc', 'desc'])) {
            $params['direction'] = 'desc';
        }

        if (argc() > 1) {
            if (observer_prohibited(true)) {
                killme();
            }

            $channel = Channel::from_username(argv(1));
            if (!$channel) {
                killme();
            }

            logger('public feed request from ' . $_SERVER['REMOTE_ADDR'] . ' for ' . $channel['channel_address']);

            echo get_public_feed($channel, $params);

            killme();
        }
    }
}
