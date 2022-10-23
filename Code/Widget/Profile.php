<?php

namespace Code\Widget;

use App;
use Code\Lib\Libprofile;

class Profile implements WidgetInterface
{

    public function widget(array $args): string
    {

        if (!App::$profile['profile_uid']) {
            return EMPTY_STR;
        }

        return Libprofile::widget(App::$profile, false, true);
    }
}
