<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Web\HTTPSig;
use Code\Lib\ActivityStreams;
use Code\Lib\Activity;
use Code\Lib\Yaml;
use Code\Lib\Channel;
use Code\Render\Theme;


class Ap_probe extends Controller
{

    public function get()
    {

        $channel = null;

        $o = replace_macros(Theme::get_template('ap_probe.tpl'), [
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
                    $channel = Channel::get_system();
                }
            }

            $x = Activity::fetch($resource, $channel, null, true);

            if ($x) {
                $o .= '<pre>' . str_replace('\\n', "\n", htmlspecialchars(json_encode($x, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) . '</pre>';
                $o .= '<pre>' . str_replace('\\n', "\n", htmlspecialchars(Yaml::encode($x))) . '</pre>';
            }
        }

        return $o;
    }
}
