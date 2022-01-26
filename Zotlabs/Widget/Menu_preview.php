<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Menu;


class Menu_preview
{

    public function widget($arr)
    {
        if (!App::$data['menu_item']) {
            return;
        }

        return Menu::render(App::$data['menu_item']);
    }
}
