<?php

namespace Code\Widget;

use App;
use Code\Lib\Apps;

class Tagcloud_wall implements WidgetInterface
{

    public function widget(array $arr): string
    {

        if ((!App::$profile['profile_uid']) || (!App::$profile['channel_hash'])) {
            return '';
        }
        if (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_stream')) {
            return '';
        }

        $limit = ((array_key_exists('limit', $arr)) ? intval($arr['limit']) : 50);
        if (Apps::system_app_installed(App::$profile['profile_uid'], 'Tagadelic')) {
            return wtagblock(App::$profile['profile_uid'], $limit, '', App::$profile['channel_hash'], 'wall');
        }

        return '';
    }
}
