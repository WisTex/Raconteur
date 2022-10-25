<?php

namespace Code\Widget;

use App;

class Vcard implements WidgetInterface
{
    public function widget(array $arguments): string
    {
        return vcard_from_xchan('', App::get_observer()) ?: '';
    }
}
