<?php

namespace Code\Widget;

use App;
use Code\Lib\Libprofile;

class Shortprofile implements WidgetInterface
{

    public function widget(array $arguments): string
    {
        if (App::$profile['profile_uid']) {
            return Libprofile::widget(App::$profile, false, true, true);
        }
        return EMPTY_STR;
    }
}
