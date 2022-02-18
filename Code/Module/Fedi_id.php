<?php

namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Render\Theme;

    
class Fedi_id extends Controller
{

    public function post()
    {
        $channel = Channel::from_id(argv(1));
        if (!$channel) {
            return;
        }
        if ($_REQUEST['address']) {
            $x = discover_by_webbie(trim($_REQUEST['address']));
            if ($x) {
                $ab = q(
                    "select * from abook where abook_xchan = '%s' and abook_channel = %d",
                    dbesc($x),
                    intval($channel['channel_id'])
                );
                if ($ab) {
                    notice(t('You are already connected with this channel.'));
                    goaway(Channel::url($channel));
                }
                $r = q(
                    "select * from xchan where xchan_hash = '%s'",
                    dbesc($x)
                );
                if ($r && $r[0]['xchan_follow']) {
                    goaway(sprintf($r[0]['xchan_follow'], urlencode(Channel::get_webfinger($channel))));
                }
            }

            notice(t('Unknown or unreachable identifier'));
            return;
        }
    }

    public function get()
    {

        return replace_macros(
            Theme::get_template('fedi_id.tpl'),
            [
                '$title' => t('Home instance'),
                '$address' => ['address', t('Enter your channel address or fediverse ID (e.g. channel@example.com)'), '', t('If you do not have a fediverse ID, please use your browser \'back\' button to return to the previous page')],
                '$action' => 'fedi_id/' . argv(1),
                '$method' => 'post',
                '$submit' => t('Connect')
            ]
        );
    }
}
