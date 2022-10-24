<?php

namespace Code\Widget;

use Code\Lib\Libzotdir;

class Dirsort implements WidgetInterface
{
    public function widget(array $arguments): string
    {
        if (intval($_REQUEST['suggest'])) {
            return EMPTY_STR;
        }
        return Libzotdir::dir_sort_links();
    }
}
