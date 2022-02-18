<?php

namespace Code\Widget;

use Code\Render\Theme;


class Chatroom_members
{

    // The actual contents are filled in via AJAX

    public function widget()
    {
        return replace_macros(Theme::get_template('chatroom_members.tpl'), array(
            '$header' => t('Chat Members')
        ));
    }
}
