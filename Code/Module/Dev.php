<?php

/**
 * @file Code/Module/Dev.php
 * @brief dev controller.
 *
 * Controller for the /dev/ area.
 */

namespace Code\Module;

use Code\Web\Controller;
use Code\Web\SubModule;
use Code\Lib\Navbar;

/**
 * @brief dev area.
 *
 */
class Dev extends Controller
{
    private SubModule $sm;

    public function __construct()
    {
        $this->sm = new SubModule();
    }

    public function init()
    {
        if (argc() > 1) {
            $this->sm->call('init');
        }
    }


    public function post()
    {
        if (argc() > 1) {
            $this->sm->call('post');
        }
    }

    /**
     * @return string
     */

    public function get() : string
    {
        /*
         * Page content
         */

        Navbar::set_selected('Dev');

        $o = '';

        if (argc() > 1) {
            $o = $this->sm->call('get');
            if ($o === false) {
                notice(t('Item not found.'));
            }
        }
        if (is_ajax()) {
            echo $o;
            killme();
        }
        return $o;
    }

}
