<?php

namespace Code\Module\Dev;

use Code\Web\Controller;
use App;
use Code\Render\Theme;


class Serialize extends Controller
{

    public function get()
    {

        if (!empty($_REQUEST['text'])) {
            $stext = serialize(json_decode($_REQUEST['text'],true));
        }
        if (!empty($_REQUEST['stext'])) {
            $text = json_encode(unserialize($_REQUEST['stext']), JSON_PRETTY_PRINT);
        }

        $o = replace_macros(Theme::get_template('serialize.tpl'), [
            '$page_title' => t('Serialize/De-serialize'),
            '$text' => ['text', t('text to serialize'), $text, EMPTY_STR],
            '$stext' => [ 'stext', t('text to unserialize'), $stext, EMPTY_STR],
            '$submit' => t('Submit')
        ]);

        return $o;
    }
}
