<?php

namespace Code\Widget;

class Dirtags implements WidgetInterface
{

    public function widget(array $arguments): string
    {
        return dir_tagblock(z_root() . '/directory', null);
    }
}
