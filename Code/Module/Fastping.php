<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Enotify;
use Code\Lib\Apps;
use Code\Lib\PConfig;

require_once('include/bbcode.php');

/**
 * @brief Fastping Controller.
 * Called from the client at regular intervals to check for updates from the server
 *
 */
class Fastping extends Controller
{

    /**
     * @brief do several updates when pinged.
     *
     * This function does several tasks. Whenever called it checks for new messages,
     * introductions, notifications, etc. and returns a json with the results.
     *
     * @result JSON
     */

    public function init()
    {

        $result['notice'] = [];
        $result['info'] = [];

        $vnotify = (-1);

        $result['invalid'] = ((isset($_GET['uid']) && intval($_GET['uid'])) && (intval($_GET['uid']) != local_channel()) ? 1 : 0);

        if (local_channel()) {
            $vnotify = get_pconfig(local_channel(), 'system', 'vnotify', (-1));
        }

        /**
         * Send all system messages (alerts) to the browser.
         * Some are marked as informational and some represent
         * errors or serious notifications. These typically
         * will popup on the current page (no matter what page it is)
         */

        if (x($_SESSION, 'sysmsg')) {
            foreach ($_SESSION['sysmsg'] as $m) {
                $result['notice'][] = array('message' => $m);
            }
            unset($_SESSION['sysmsg']);
        }
        if (x($_SESSION, 'sysmsg_info')) {
            foreach ($_SESSION['sysmsg_info'] as $m) {
                $result['info'][] = array('message' => $m);
            }
            unset($_SESSION['sysmsg_info']);
        }
        if (!($vnotify & VNOTIFY_INFO)) {
            $result['info'] = [];
        }
        if (!($vnotify & VNOTIFY_ALERT)) {
            $result['notice'] = [];
        }

        json_return_and_die($result);
    }
}
