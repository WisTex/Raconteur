<?php

namespace Code\Widget;

use App;
use Code\Lib\Channel;

require_once('include/photos.php');

class Photo_albums
{

    public function widget($arr)
    {

        if (!App::$profile['profile_uid']) {
            return EMPTY_STR;
        }

        $channel = Channel::from_id(App::$profile['profile_uid']);

        if ((!$channel) || (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_storage'))) {
            return EMPTY_STR;
        }

        $sortkey = ((array_key_exists('sortkey', $arr)) ? $arr['sortkey'] : 'display_path');
        $direction = ((array_key_exists('direction', $arr)) ? $arr['direction'] : 'asc');

        return photos_album_widget($channel, App::get_observer(), $sortkey, $direction);
    }
}
