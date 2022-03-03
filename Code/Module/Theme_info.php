<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Render\Theme;

class Theme_info extends Controller
{

    public function get()
    {
        $theme = argv(1);
        if (!$theme) {
            killme();
        }

        $schemalist = [];

        $theme_config = "";
        if (($themeconfigfile = $this->get_theme_config_file($theme)) != null) {
            require_once($themeconfigfile);
            if (class_exists('\\Code\\Theme\\' . ucfirst($theme) . 'Config')) {
                $clsname = '\\Code\\Theme\\' . ucfirst($theme) . 'Config';
                $th_config = new $clsname();
                $schemas = $th_config->get_schemas();
                if ($schemas) {
                    foreach ($schemas as $k => $v) {
                        $schemalist[] = ['key' => $k, 'val' => $v];
                    }
                }
                $theme_config = $th_config->get();
            }
        }
        $info = Theme::get_info($theme);
        if ($info) {
            // unfortunately there will be no translation for this string
            $desc = $info['description'];
            $version = $info['version'];
            $credits = $info['credits'];
        } else {
            $desc = '';
            $version = '';
            $credits = '';
        }

        $ret = [
            'theme' => $theme,
            'img' => Theme::get_screenshot($theme),
            'desc' => $desc,
            'version' => $version,
            'credits' => $credits,
            'schemas' => $schemalist,
            'config' => $theme_config
        ];
        json_return_and_die($ret);
    }


    public function get_theme_config_file($theme)
    {

        $base_theme = App::$theme_info['extends'];

        if (file_exists("view/theme/$theme/php/config.php")) {
            return "view/theme/$theme/php/config.php";
        }
        if (file_exists("view/theme/$base_theme/php/config.php")) {
            return "view/theme/$base_theme/php/config.php";
        }
        return null;
    }
}