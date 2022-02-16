<?php

namespace Code\Widget;

use App;
use Code\Lib\Libprofile;

class Shortprofile
{

    public function widget($arr)
    {
        if (App::$profile['profile_uid']) {
            return Libprofile::widget(App::$profile, observer_prohibited(), true, true);
        }
        return EMPTY_STR;
    }
}
