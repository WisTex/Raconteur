<?php

namespace Code\Widget;

use Code\Lib\Libzotdir;

class Dirsort
{
    public function widget($arr)
    {
        if (intval($_REQUEST['suggest'])) {
            return EMPTY_STR;
        }
        return Libzotdir::dir_sort_links();
    }
}
