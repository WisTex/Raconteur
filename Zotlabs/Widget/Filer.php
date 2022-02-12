<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Render\Theme;


class Filer
{

    public function widget($arr)
    {
        if (!local_channel()) {
            return '';
        }


        $selected = ((x($_REQUEST, 'file')) ? $_REQUEST['file'] : '');

        $terms = [];
        $r = q(
            "select distinct term from term where uid = %d and ttype = %d order by term asc",
            intval(local_channel()),
            intval(TERM_FILE)
        );
        if (!$r) {
            return;
        }

        foreach ($r as $rr) {
            $terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));
        }

        return replace_macros(Theme::get_template('fileas_widget.tpl'), array(
            '$title' => t('Saved Folders'),
            '$desc' => '',
            '$sel_all' => (($selected == '') ? 'selected' : ''),
            '$all' => t('Everything'),
            '$terms' => $terms,
            '$base' => z_root() . '/' . App::$cmd
        ));
    }
}
