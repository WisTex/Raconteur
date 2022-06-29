<?php

namespace Code\Module\Dev;

use App;
use Code\Lib\Url;
use Code\Lib\Zotfinger;
use Code\Web\Controller;
use Code\Web\HTTPSig;
use Code\Render\Theme;


class Zot_probe extends Controller
{

    public function get()
    {

        $o = replace_macros(Theme::get_template('zot_probe.tpl'), [
            '$page_title' => t('Zot6 Probe Diagnostic'),
            '$resource' => ['resource', t('Object URL'), $_REQUEST['resource'], EMPTY_STR],
            '$authf' => ['authf', t('Authenticated fetch'), $_REQUEST['authf'], EMPTY_STR, [t('No'), t('Yes')]],
            '$submit' => t('Submit')
        ]);

        if (x($_GET, 'resource')) {
            $resource = $_GET['resource'];
            $channel = (($_GET['authf']) ? App::get_channel() : null);

            $x = Zotfinger::exec($resource, $channel);

            $o .= '<pre>' . htmlspecialchars(print_array($x)) . '</pre>';

            $headers = 'Accept: application/x-nomad+json, application/x-zot+json, application/jrd+json, application/json';

            $x = Url::get($resource, ['headers' => [$headers]]);

            if ($x['success']) {
                $o .= '<pre>' . htmlspecialchars($x['header']) . '</pre>' . EOL;

                $o .= 'verify returns: ' . str_replace("\n", EOL, print_r(HTTPSig::verify($x, EMPTY_STR, 'zot6'), true)) . EOL;

                $o .= '<pre>' . htmlspecialchars(json_encode(json_decode($x['body']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>' . EOL;
            }
        }
        return $o;
    }
}
