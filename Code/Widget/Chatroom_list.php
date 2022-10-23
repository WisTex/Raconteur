<?php

namespace Code\Widget;

use App;
use Code\Lib\Chatroom;
use Code\Render\Theme;


class Chatroom_list implements WidgetInterface
{

    public function widget(array $arr): string
    {
        if (!App::$profile) {
            return '';
        }

        $r = Chatroom::roomlist(App::$profile['profile_uid']);

        if ($r) {
            return replace_macros(Theme::get_template('chatroomlist.tpl'), [
                '$header' => t('Chatrooms'),
                '$baseurl' => z_root(),
                '$nickname' => App::$profile['channel_address'],
                '$items' => $r,
                '$overview' => t('Overview')
            ]);
        }
        return '';
    }
}
