<?php

namespace Code\Widget;

use App;
use Code\Lib\Libprofile;

class Fullprofile implements WidgetInterface
{

    public function widget(array $arr): string
    {

        if (!App::$profile['profile_uid']) {
            return EMPTY_STR;
        }

        return Libprofile::widget(App::$profile, false);
    }
}
