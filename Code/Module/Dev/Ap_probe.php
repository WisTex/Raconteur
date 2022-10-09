<?php

namespace Code\Module\Dev;

use App;
use Code\Web\Controller;
use Code\Lib\ActivityStreams;
use Code\Lib\Activity;
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

            $j = Activity::fetch($resource, $channel, true);

            if ($j) {
                $o .= '<pre>' . str_replace('\\n', "\n", htmlspecialchars(json_encode($j, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) . '</pre>';
            }

            if (isset($j['type'])) {
                if (!ActivityStreams::is_an_actor($j['type'])) {
                    $AS = new ActivityStreams($j, null, true);
                    if ($AS->is_valid() && isset($AS->data['type'])) {
                        if (is_array($AS->obj)
                                && isset($AS->obj['type'])
                                && !str_contains($AS->obj['type'], 'Collection')) {
                            $item = Activity::decode_note($AS, true);
                            if ($item) {
                                $o .= '<pre>' . str_replace('\\n', "\n", htmlspecialchars(json_encode($item, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) . '</pre>';
                                require_once('include/conversation.php');
                                $item['attach'] = json_encode($item['attach']);
                                $items  = [$item];
                                xchan_query($items);
                                $o .= conversation($items, 'search', false, 'preview');
                            }
                        }
                    }
                }
            }
        }
        return $o;
    }
}
