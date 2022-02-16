<?php

namespace Code\Module;

/** @file */

use Code\Lib\Libsync;
use Code\Web\Controller;
use Code\Lib\Apps;

class Notes extends Controller
{

    public function init()
    {

        if (!local_channel()) {
            return;
        }

        $ret = array('success' => true);
        if (array_key_exists('note_text', $_REQUEST)) {
            $body = escape_tags($_REQUEST['note_text']);

            // I've had my notes vanish into thin air twice in four years.
            // Provide a backup copy if there were contents previously
            // and there are none being saved now.

            if (!$body) {
                $old_text = get_pconfig(local_channel(), 'notes', 'text');
                if ($old_text) {
                    set_pconfig(local_channel(), 'notes', 'text.bak', $old_text);
                }
            }
            set_pconfig(local_channel(), 'notes', 'text', $body);


            // push updates to channel clones

            if ((argc() > 1) && (argv(1) === 'sync')) {
                Libsync::build_sync_packet();
            }

            logger('notes saved.', LOGGER_DEBUG);
            json_return_and_die($ret);
        }
    }

    public function get()
    {

        $desc = t('This app allows you to create private notes for your personal use.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Notes'))) {
            return $text;
        }

        $desc = t('This app is installed. The Notes tool can be found on your stream page.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        return $text;
    }
}
