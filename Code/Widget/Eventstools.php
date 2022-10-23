<?php

namespace Code\Widget;

use Code\Render\Theme;


class Eventstools implements WidgetInterface
{

    public function widget(array $arr): string
    {

        if (!local_channel()) {
            return '';
        }

        return replace_macros(Theme::get_template('events_tools_side.tpl'), array(
            '$title' => t('Events Tools'),
            '$export' => t('Export Calendar'),
            '$import' => t('Import Calendar'),
            '$submit' => t('Submit')
        ));
    }
}
