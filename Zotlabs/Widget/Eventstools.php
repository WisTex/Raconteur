<?php

namespace Zotlabs\Widget;

use Zotlabs\Render\Theme;


class Eventstools
{

    public function widget($arr)
    {

        if (!local_channel()) {
            return;
        }

        return replace_macros(Theme::get_template('events_tools_side.tpl'), array(
            '$title' => t('Events Tools'),
            '$export' => t('Export Calendar'),
            '$import' => t('Import Calendar'),
            '$submit' => t('Submit')
        ));
    }
}
