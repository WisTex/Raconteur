<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;


class Ap_probe extends Controller
{

    public function get()
    {

        $channel = null;

        $o = replace_macros(get_markup_template('ap_probe.tpl'), [
            '$page_title' => t('ActivityPub Probe Diagnostic'),
            '$resource' => ['resource', t('Object URL'), $_REQUEST['resource'], EMPTY_STR],
            '$authf' => ['authf', t('Authenticated fetch'), $_REQUEST['authf'], EMPTY_STR, [t('No'), t('Yes')]],
            '$submit' => t('Submit')
        ]);

        if (x($_REQUEST, 'resource')) {
            $resource = $_REQUEST['resource'];
            if ($_REQUEST['authf']) {
                $channel = App::get_channel();
                if (!$channel) {
                    $channel = get_sys_channel();
                }
            }

            $x = Activity::fetch($resource, $channel, null, true);

            if ($x) {
                $o .= '<pre>' . str_replace('\\n', "\n", htmlspecialchars(json_encode($x, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) . '</pre>';
            }

        }

        return $o;
    }

}
