<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Render\Theme;


class Oexchange extends Controller
{

    public function init()
    {

        if ((argc() > 1) && (argv(1) === 'xrd')) {
            echo replace_macros(Theme::get_template('oexchange_xrd.tpl'), ['$base' => z_root()]);
            killme();
        }
    }

    public function get()
    {
        if (!local_channel()) {
            if (remote_channel()) {
                $observer = App::get_observer();
                if ($observer && $observer['xchan_url']) {
                    $parsed = @parse_url($observer['xchan_url']);
                    if (!$parsed) {
                        notice(t('Unable to find your site.') . EOL);
                        return;
                    }
                    $url = $parsed['scheme'] . '://' . $parsed['host'] . (($parsed['port']) ? ':' . $parsed['port'] : '');
                    $url .= '/oexchange';
                    $result = z_post_url($url, $_REQUEST);
                    json_return_and_die($result);
                }
            }

            return login(false);
        }

        if ((argc() > 1) && argv(1) === 'done') {
            info(t('Post successful.') . EOL);
            return;
        }

        $url = (((x($_REQUEST, 'url')) && strlen($_REQUEST['url']))
            ? urlencode(notags(trim($_REQUEST['url']))) : '');
        $title = (((x($_REQUEST, 'title')) && strlen($_REQUEST['title']))
            ? '&title=' . urlencode(notags(trim($_REQUEST['title']))) : '');
        $description = (((x($_REQUEST, 'description')) && strlen($_REQUEST['description']))
            ? '&description=' . urlencode(notags(trim($_REQUEST['description']))) : '');
        $tags = (((x($_REQUEST, 'tags')) && strlen($_REQUEST['tags']))
            ? '&tags=' . urlencode(notags(trim($_REQUEST['tags']))) : '');

        $ret = z_fetch_url(z_root() . '/linkinfo?f=&url=' . $url . $title . $description . $tags);

        if ($ret['success']) {
            $s = $ret['body'];
        }

        if (!strlen($s)) {
            return;
        }

        $post = [];

        $post['profile_uid'] = local_channel();
        $post['return'] = '/oexchange/done';
        $post['body'] = $s;
        $post['type'] = 'wall';

        $_REQUEST = $post;
        $mod = new Item();
        $mod->post();
    }
}
