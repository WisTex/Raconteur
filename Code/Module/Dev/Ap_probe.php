<?php

namespace Code\Module\Dev;

use App;
use Code\Web\Controller;
use Code\Lib\ActivityStreams;
use Code\Lib\Activity;
use Code\Lib\Channel;
use Code\Render\Theme;

require_once('include/conversation.php');

class Ap_probe extends Controller
{

    public function get()
    {
        $channel = null;

        $html = replace_macros(Theme::get_template('ap_probe.tpl'), [
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
                $html .= '<pre>' . str_replace('\\n', "\n", htmlspecialchars(json_encode($j, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) . '</pre>';
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
                                $html .= '<pre>' . str_replace('\\n', "\n", htmlspecialchars(json_encode($item, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) . '</pre>';

                                $item['attach'] = json_encode($item['attach']);
                                $items  = [$item];
                                xchan_query($items);
                                $html .= conversation($items, 'search', false, 'preview');
                            }
                        }
                    }
                }
            }
        }
        return $html;
    }
}
