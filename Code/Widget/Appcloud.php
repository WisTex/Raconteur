<?php

namespace Code\Widget;

class Appcloud implements WidgetInterface
{

    public function widget(array $arr): string
    {
        if (!local_channel()) {
            return '';
        }
        return app_tagblock(z_root() . '/apps');
    }
}
