<?php

/** @file */

namespace Zotlabs\Render;

use Smarty;
use App;

class SmartyInterface extends Smarty
{

    public $filename;

    public function __construct()
    {
        parent::__construct();

        $theme = Theme::current();
        $thname = $theme[0];

        // setTemplateDir can be set to an array, which Smarty will parse in order.
        // The order is thus very important here

        $template_dirs = ['theme' => "view/theme/$thname/tpl/"];
        if (x(App::$theme_info, "extends")) {
            $template_dirs = $template_dirs + ['extends' => "view/theme/" . App::$theme_info["extends"] . '/tpl/'];
        }
        $template_dirs = $template_dirs + array('base' => 'view/tpl/');
        $this->setTemplateDir($template_dirs);

        // Cannot use get_config() here because it is called during installation when there is no DB.
        // FIXME: this may leak private information such as system pathnames.

        $basecompiledir = ((array_key_exists('smarty3_folder', App::$config['system']))
            ? App::$config['system']['smarty3_folder'] : '');
        if (!$basecompiledir) {
            $basecompiledir = str_replace('Zotlabs', '', dirname(__DIR__)) . TEMPLATE_BUILD_PATH;
        }
        if (!is_dir($basecompiledir)) {
            @os_mkdir(TEMPLATE_BUILD_PATH, STORAGE_DEFAULT_PERMISSIONS, true);
        }
        if (!is_dir($basecompiledir)) {
            echo "<b>ERROR:</b> folder <tt>$basecompiledir</tt> does not exist.";
            killme();
        }

        if (!is_writable($basecompiledir)) {
            echo "<b>ERROR:</b> folder <tt>$basecompiledir</tt> must be writable by webserver.";
            killme();
        }
        App::$config['system']['smarty3_folder'] = $basecompiledir;

        $this->setCompileDir($basecompiledir . '/compiled/');
        $this->setConfigDir($basecompiledir . '/config/');
        $this->setCacheDir($basecompiledir . '/cache/');

        $this->left_delimiter = App::get_template_ldelim('smarty3');
        $this->right_delimiter = App::get_template_rdelim('smarty3');

        // Don't report errors so verbosely
        $this->error_reporting = (E_ERROR | E_PARSE);
    }

    public function parsed($template = '')
    {
        if ($template) {
            return $this->fetch('string:' . $template);
        }
        return $this->fetch('file:' . $this->filename);
    }
}
