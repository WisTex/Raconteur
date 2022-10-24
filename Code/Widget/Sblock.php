<?php

namespace Code\Widget;

use App;
use Code\Render\Theme;


class Sblock implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        if (!local_channel()) {
            return EMPTY_STR;
        }

        return replace_macros(Theme::get_template('superblock_widget.tpl'), [
            '$connect' => t('Block channel or site'),
            '$desc' => t('Enter channel address or URL'),
            '$hint' => t('Examples: bob@example.com, https://example.com/barbara'),
            '$follow' => t('Block'),
            '$abook_usage_message' => '',
        ]);
    }
}
