<?php

namespace Code\Widget;

class Dirtags implements WidgetInterface
{

    public function widget(array $arr): string
    {
        return dir_tagblock(z_root() . '/directory', null);
    }
}
