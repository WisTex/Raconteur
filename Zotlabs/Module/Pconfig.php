<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Pconfig extends Controller
{

    public function post()
    {

        if (!local_channel()) {
            return;
        }

        if (isset($_SESSION['delegate']) && $_SESSION['delegate']) {
            return;
        }

        check_form_security_token_redirectOnErr('/pconfig', 'pconfig');

        $cat = trim(escape_tags((isset($_POST['cat']) && $_POST['cat']) ? $_POST['cat'] : EMPTY_STR));
        $k = trim(escape_tags((isset($_POST['k']) && $_POST['k']) ? $_POST['k'] : EMPTY_STR));
        $v = trim((isset($_POST['v']) && $_POST['v']) ? $_POST['v'] : EMPTY_STR);
        $aj = intval((isset($_POST['aj']) && $_POST['aj']) ? $_POST['aj'] : 0);

        // Do not store "serialized" data received in the $_POST

        if (preg_match('|^a:[0-9]+:{.*}$|s', $v)) {
            return;
        }

        if (in_array(argv(2), $this->disallowed_pconfig())) {
            notice(t('This setting requires special processing and editing has been blocked.') . EOL);
            return;
        }

        if (strpos($k, 'password') !== false) {
            $v = obscurify($v);
        }

        set_pconfig(local_channel(), $cat, $k, $v);
        Libsync::build_sync_packet();

        if ($aj) {
            killme();
        }

        goaway(z_root() . '/pconfig/' . $cat . '/' . $k);
    }


    public function get()
    {

        if (!local_channel()) {
            return login();
        }

        $content = '<h3>' . t('Configuration Editor') . '</h3>';
        $content .= '<div class="descriptive-paragraph">' . t('Warning: Changing some settings could render your channel inoperable. Please leave this page unless you are comfortable with and knowledgeable about how to correctly use this feature.') . '</div>' . EOL . EOL;


        if (argc() == 3) {
            $content .= '<a href="pconfig">pconfig[' . local_channel() . ']</a>' . EOL;
            $content .= '<a href="pconfig/' . escape_tags(argv(1)) . '">pconfig[' . local_channel() . '][' . escape_tags(argv(1)) . ']</a>' . EOL . EOL;
            $content .= '<a href="pconfig/' . escape_tags(argv(1)) . '/' . escape_tags(argv(2)) . '" >pconfig[' . local_channel() . '][' . escape_tags(argv(1)) . '][' . escape_tags(argv(2)) . ']</a> = ' . get_pconfig(local_channel(), escape_tags(argv(1)), escape_tags(argv(2))) . EOL;

            if (in_array(argv(2), $this->disallowed_pconfig())) {
                notice(t('This setting requires special processing and editing has been blocked.') . EOL);
                return $content;
            } else {
                $content .= $this->pconfig_form(escape_tags(argv(1)), escape_tags(argv(2)));
            }
        }


        if (argc() == 2) {
            $content .= '<a href="pconfig">pconfig[' . local_channel() . ']</a>' . EOL;
            load_pconfig(local_channel(), escape_tags(argv(1)));
            if (App::$config[local_channel()][escape_tags(argv(1))]) {
                foreach (App::$config[local_channel()][escape_tags(argv(1))] as $k => $x) {
                    $content .= '<a href="pconfig/' . escape_tags(argv(1)) . '/' . $k . '" >pconfig[' . local_channel() . '][' . escape_tags(argv(1)) . '][' . $k . ']</a> = ' . escape_tags($x) . EOL;
                }
            }
        }

        if (argc() == 1) {
            $r = q("select * from pconfig where uid = " . local_channel());
            if ($r) {
                foreach ($r as $rr) {
                    $content .= '<a href="' . 'pconfig/' . escape_tags($rr['cat']) . '/' . escape_tags($rr['k']) . '" >pconfig[' . local_channel() . '][' . escape_tags($rr['cat']) . '][' . escape_tags($rr['k']) . ']</a> = ' . escape_tags($rr['v']) . EOL;
                }
            }
        }
        return $content;
    }


    public function pconfig_form($cat, $k)
    {

        $o = '<form action="pconfig" method="post" >';
        $o .= '<input type="hidden" name="form_security_token" value="' . get_form_security_token('pconfig') . '" />';

        $v = get_pconfig(local_channel(), $cat, $k);
        if (strpos($k, 'password') !== false) {
            $v = unobscurify($v);
        }

        $o .= '<input type="hidden" name="cat" value="' . $cat . '" />';
        $o .= '<input type="hidden" name="k" value="' . $k . '" />';


        if (strpos($v, "\n")) {
            $o .= '<textarea name="v" >' . escape_tags($v) . '</textarea>';
        } else {
            if (is_array($v)) {
                $o .= '<code><pre>' . "\n" . print_array($v) . '</pre></code>';
                $o .= '<input type="hidden" name="v" value="' . serialise($v) . '" />';
            } else {
                $o .= '<input type="text" name="v" value="' . escape_tags($v) . '" />';
            }
        }
        $o .= EOL . EOL;
        $o .= '<input type="submit" name="submit" value="' . t('Submit') . '" />';
        $o .= '</form>';

        return $o;
    }


    public function disallowed_pconfig()
    {
        return array(
            'permissions_role'
        );
    }
}
