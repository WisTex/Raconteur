<?php

namespace Code\Widget;

use App;
use Code\Lib\Channel;

require_once('include/photos.php');

class Photo_albums implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        if (!App::$profile['profile_uid']) {
            return EMPTY_STR;
        }

        $channel = Channel::from_id(App::$profile['profile_uid']);

        if ((!$channel) || (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_storage'))) {
            return EMPTY_STR;
        }

        $sortkey = ((array_key_exists('sortkey', $arguments)) ? $arguments['sortkey'] : 'display_path');
        $direction = ((array_key_exists('direction', $arguments)) ? $arguments['direction'] : 'asc');

        return photos_album_widget($channel, App::get_observer(), $sortkey, $direction);
    }
}
