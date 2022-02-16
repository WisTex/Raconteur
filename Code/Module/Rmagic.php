<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Extend\Hook;
use Code\Render\Theme;


class Rmagic extends Controller
{

    public function init()
    {

        if (local_channel()) {
            goaway(z_root());
        }

        $me = Channel::get_my_address();
        if ($me) {
            $r = q(
                "select hubloc_url from hubloc where hubloc_addr = '%s' limit 1",
                dbesc($me)
            );
            if ($r) {
                if ($r[0]['hubloc_url'] === z_root()) {
                    goaway(z_root() . '/login');
                }
                $dest = bin2hex(z_root() . '/' . str_replace(['rmagic', 'zid='], ['', 'zid_='], App::$query_string));
                goaway($r[0]['hubloc_url'] . '/magic' . '?f=&owa=1&bdest=' . $dest);
            }
        }
    }

    public function post()
    {

        $address = trim($_REQUEST['address']);

        if (strpos($address, '@') === false) {
            $arr = ['address' => $address];
            Hook::call('reverse_magic_auth', $arr);

            // if they're still here...
            notice(t('Authentication failed.') . EOL);
            return;
        } else {
            // Presumed Red identity. Perform reverse magic auth

            if (strpos($address, '@') === false) {
                notice('Invalid address.');
                return;
            }

            $r = null;
            if ($address) {
                $r = q(
                    "select hubloc_url from hubloc where hubloc_addr = '%s' limit 1",
                    dbesc($address)
                );
            }
            if ($r) {
                $url = $r[0]['hubloc_url'];
            } else {
                $url = 'https://' . substr($address, strpos($address, '@') + 1);
            }

            if ($url) {
                if ($_SESSION['return_url']) {
                    $dest = bin2hex(z_root() . '/' . str_replace('zid=', 'zid_=', $_SESSION['return_url']));
                } else {
                    $dest = bin2hex(z_root() . '/' . str_replace(['rmagic', 'zid='], ['', 'zid_='], App::$query_string));
                }
                goaway($url . '/magic' . '?f=&owa=1&bdest=' . $dest);
            }
        }
    }


    public function get()
    {
        return replace_macros(
            Theme::get_template('rmagic.tpl'),
            [
                '$title' => t('Remote Authentication'),
                '$address' => ['address', t('Enter your channel address (e.g. channel@example.com)'), '', ''],
                '$action' => 'rmagic',
                '$method' => 'post',
                '$submit' => t('Authenticate')
            ]
        );
    }
}
