<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Channel;

class Zcard
{

    public function widget($args)
    {
        $channel = Channel::from_id(App::$profile_uid);
        return Channel::get_zcard($channel, get_observer_hash(), array('width' => 875));
    }
}
