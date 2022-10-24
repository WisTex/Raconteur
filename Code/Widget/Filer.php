<?php

namespace Code\Widget;

use App;
use Code\Render\Theme;


class Filer implements WidgetInterface
{

    public function widget(array $arguments): string
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
            return '';
        }

        foreach ($r as $rr) {
            $terms[] = ['name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : '')];
        }

        return replace_macros(Theme::get_template('fileas_widget.tpl'), [
            '$title' => t('Saved Folders'),
            '$desc' => '',
            '$sel_all' => (($selected == '') ? 'selected' : ''),
            '$all' => t('Everything'),
            '$terms' => $terms,
            '$base' => z_root() . '/' . App::$cmd
        ]);
    }
}
