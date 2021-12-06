<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Libprofile;

class Profile
{

    public function widget($args)
    {

        if (!App::$profile['profile_uid']) {
            return EMPTY_STR;
        }

        return Libprofile::widget(App::$profile, observer_prohibited(), true);
    }
}
