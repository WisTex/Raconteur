<?php

namespace Zotlabs\Widget;

use App;

class Vcard
{
    public function widget($arr)
    {
        return vcard_from_xchan('', App::get_observer());
    }
}
