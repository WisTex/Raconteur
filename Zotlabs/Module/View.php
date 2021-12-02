<?php
namespace Zotlabs\Module;
use Zotlabs\Web\Controller;

/**
 * load view/theme/$current_theme/style.php with Hubzilla context
 */
class View extends Controller
{

    public function init()
    {
        header("Content-Type: text/css");

        $theme = argv(2);
        $THEMEPATH = "view/theme/$theme";
        if (file_exists("view/theme/$theme/php/style.php"))
            require_once("view/theme/$theme/php/style.php");
        killme();
    }

}
