<?php

namespace Code\Widget;

use Code\Render\Theme;


class Chatroom_members implements WidgetInterface
{

    // The actual contents are filled in via AJAX

    public function widget(array $arr): string
    {
        return replace_macros(Theme::get_template('chatroom_members.tpl'), [
            '$header' => t('Chat Members')
        ]);
    }
}
