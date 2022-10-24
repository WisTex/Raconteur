<?php

namespace Code\Widget;

use App;
use Code\Lib\Features;
use Code\Render\Theme;


class Bookmarkedchats implements WidgetInterface

{

    public function widget(array $arguments): string
    {

        if (!Features::enabled(App::$profile['profile_uid'], 'ajaxchat')) {
            return '';
        }

        $h = get_observer_hash();
        if (!$h) {
            return '';
        }
        $r = q(
            "select xchat_url, xchat_desc from xchat where xchat_xchan = '%s' order by xchat_desc",
            dbesc($h)
        );
        if ($r) {
            for ($x = 0; $x < count($r); $x++) {
                $r[$x]['xchat_url'] = zid($r[$x]['xchat_url']);
            }
        }
        return replace_macros(Theme::get_template('bookmarkedchats.tpl'), [
            '$header' => t('Bookmarked Chatrooms'),
            '$rooms' => $r
        ]);
    }
}
