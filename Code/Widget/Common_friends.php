<?php

namespace Code\Widget;

use App;
use Code\Render\Theme;
use Code\Lib\Socgraph;

class Common_friends implements WidgetInterface
{

    public function widget(array $arr): string
    {

        if (
            (!App::$profile['profile_uid'])
            || (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_contacts'))
        ) {
            return '';
        }

        return self::common_friends_visitor_widget(App::$profile['profile_uid']);
    }

    public static function common_friends_visitor_widget($profile_uid, $cnt = 25)
    {

        if (local_channel() == $profile_uid) {
            return false;
        }

        $observer_hash = get_observer_hash();

        if ((!$observer_hash) || (!perm_is_allowed($profile_uid, $observer_hash, 'view_contacts'))) {
            return false;
        }

        $t = Socgraph::count_common_friends($profile_uid, $observer_hash);

        if (!$t) {
            return false;
        }

        $r = Socgraph::common_friends($profile_uid, $observer_hash, 0, $cnt, true);

        return replace_macros(Theme::get_template('remote_friends_common.tpl'), [
            '$desc' => t('Common Connections'),
            '$base' => z_root(),
            '$uid' => $profile_uid,
            '$cid' => $observer_hash,
            '$linkmore' => (($t > $cnt) ? 'true' : ''),
            '$more' => sprintf(t('View all %d common connections'), $t),
            '$items' => $r
        ]);
    }
}
