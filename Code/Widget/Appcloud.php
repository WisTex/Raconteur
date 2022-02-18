<?php

namespace Code\Widget;

class Appcloud
{

    public function widget($arr)
    {
        if (!local_channel()) {
            return '';
        }
        return app_tagblock(z_root() . '/apps');
    }
}
