<?php

/**
 * @file Code/Module/Dev.php
 * @brief dev controller.
 *
 * Controller for the /dev/ area.
 */

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Web\SubModule;
use Code\Lib\Config;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Lib\Addon;
use Code\Render\Theme;

    
/**
 * @brief dev area.
 *
 */
class Dev extends Controller
{

    private $sm = null;

    public function __construct()
    {
        $this->sm = new SubModule();
    }

    public function init()
    {

        logger('dev_init', LOGGER_DEBUG);

        if (argc() > 1) {
            $this->sm->call('init');
        }
    }


    public function post()
    {

        logger('dev_post', LOGGER_DEBUG);

        if (argc() > 1) {
            $this->sm->call('post');
        }
    }

    /**
     * @return string
     */

    public function get()
    {

        logger('dev_content', LOGGER_DEBUG);

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
        } else {
            return $o;
        }
    }

}
