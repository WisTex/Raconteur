<?php

namespace Code\Widget;

use App;
use Code\Lib\Menu;


class Menu_preview implements WidgetInterface
{

    public function widget(array $arr): string
    {
        if (!App::$data['menu_item']) {
            return '';
        }

        return Menu::render(App::$data['menu_item']);
    }
}
