<?php

namespace Code\Widget;

use App;
use Code\Lib\Channel;

class Zcard implements WidgetInterface
{

    public function widget(array $arr): string
    {
        $channel = Channel::from_id(App::$profile_uid);
        return Channel::get_zcard($channel, get_observer_hash(), ['width' => 875]);
    }
}
