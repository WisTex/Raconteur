<?php

namespace Code\Widget;

use App;

class Vcard implements WidgetInterface
{
    public function widget(array $arr): string
    {
        return vcard_from_xchan('', App::get_observer());
    }
}
