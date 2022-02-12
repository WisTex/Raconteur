<?php

namespace Zotlabs\Widget;

use Zotlabs\Lib\Apps;
use Zotlabs\Render\Theme;


class Notes
{

    public function widget($arr)
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
