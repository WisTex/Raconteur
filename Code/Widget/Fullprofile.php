<?php

namespace Code\Widget;

use App;
use Code\Lib\Libprofile;

class Fullprofile
{

    public function widget($arr)
    {

        if (!App::$profile['profile_uid']) {
            return EMPTY_STR;
        }

        return Libprofile::widget(App::$profile, observer_prohibited());
    }
}
