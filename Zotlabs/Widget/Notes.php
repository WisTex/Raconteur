<?php

namespace Zotlabs\Widget;

use Zotlabs\Lib\Apps;

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

        $o = replace_macros(get_markup_template('notes.tpl'), [
            '$banner' => t('Notes'),
            '$text' => $text,
            '$save' => t('Save'),
        ]);

        return $o;
    }
}
