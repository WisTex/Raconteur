<?php

namespace Code\Widget;

use Code\Lib\Apps;
use Code\Render\Theme;


class Notes implements WidgetInterface
{

    public function widget(array $arr): string
    {
        if (!local_channel()) {
            return '';
        }
        if (!Apps::system_app_installed(local_channel(), 'Notes')) {
            return '';
        }

        $text = get_pconfig(local_channel(), 'notes', 'text');

        $o = replace_macros(Theme::get_template('notes.tpl'), [
            '$banner' => t('Notes'),
            '$text' => $text,
            '$save' => t('Save'),
        ]);

        return $o;
    }
}
