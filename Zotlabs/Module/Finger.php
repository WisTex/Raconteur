<?php

namespace Zotlabs\Module;

/*
 * Finger diagnostic tool.
 * Input a webfinger resource via an input form and
 * view the results as an array.
 *
 */

use Zotlabs\Web\Controller;
use Zotlabs\Lib\Webfinger;
use Zotlabs\Render\Theme;


class Finger extends Controller
{

    public function get()
    {

        $o = replace_macros(Theme::get_template('finger.tpl'), [
            '$page_title' => t('Webfinger Diagnostic'),
            '$resource' => ['resource', t('Lookup address or URL'), $_GET['resource'], EMPTY_STR],
            '$submit' => t('Submit')
        ]);

        if ($_GET['resource']) {
            $resource = trim(escape_tags($_GET['resource']));

            $result = Webfinger::exec($resource);

            $o .= '<pre>' . str_replace("\n", '<br>', print_array($result)) . '</pre>';
        }
        return $o;
    }
}
