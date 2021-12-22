<?php

namespace Zotlabs\Widget;

use App;

require_once('include/menu.php');

class Menu_preview
{

    public function widget($arr)
    {
        if (!App::$data['menu_item']) {
            return;
        }

        return menu_render(App::$data['menu_item']);
    }
}
