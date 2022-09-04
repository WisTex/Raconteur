<?php

namespace Code\Module;

use Code\Web\Controller;

class Markup extends Controller
{


    public function get()
    {

        $desc = t('This app adds editor buttons for bold, italic, underline, quote, and possibly other common richtext constructs.');

        return '<div class="section-content-info-wrapper">' . $desc . '</div>';
    }
}
