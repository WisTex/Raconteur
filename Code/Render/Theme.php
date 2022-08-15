<?php

namespace Code\Render;

use App;
use Code\Lib\Infocon;
use Code\Lib\Addon;
use Code\Lib\Yaml;
use Exception;


class Theme
{

    public static $system_theme = null;

    public static $session_theme = null;

    /**
     * @brief Array with base or fallback themes.
     */
    public static $base_themes = array('redbasic');


    /**
     * @brief Figure out the best matching theme and return it.
     *
     * The theme will depend on channel settings, mobile, session, core compatibility, etc.
     *
     * @return array
     */
    public static function current()
    {

        self::$system_theme = ((isset(App::$config['system']['theme']))
            ? App::$config['system']['theme'] : '');
        self::$session_theme = ((isset($_SESSION) && x($_SESSION, 'theme'))
            ? $_SESSION['theme'] : self::$system_theme);

        $page_theme = null;

        // Find the theme that belongs to the channel whose stuff we are looking at

        if (App::$profile_uid) {
            $r = q(
                "select channel_theme from channel where channel_id = %d limit 1",
                intval(App::$profile_uid)
            );
            if ($r) {
                $page_theme = $r[0]['channel_theme'];
            }
        }

        // Themes from Comanche layouts over-ride the channel theme

        if (array_key_exists('theme', App::$layout) && App::$layout['theme']) {
            $page_theme = App::$layout['theme'];
        }

        $chosen_theme = self::$session_theme;

        if ($page_theme) {
            $chosen_theme = $page_theme;
        }

        if (array_key_exists('theme_preview', $_GET)) {
            $chosen_theme = $_GET['theme_preview'];
        }

        // Allow theme selection of the form 'theme_name:schema_name'
        $themepair = explode(':', $chosen_theme);

        // Check if $chosen_theme is compatible with core. If not fall back to default
        $info = self::get_info($themepair[0]);
        $compatible = Addon::check_versions($info);
        if (!$compatible) {
            $chosen_theme = '';
        }

        if ($chosen_theme && (file_exists('view/theme/' . $themepair[0] . '/css/style.css') || file_exists('view/theme/' . $themepair[0] . '/php/style.php'))) {
            return ($themepair);
        }

        foreach (self::$base_themes as $t) {
            if (
                file_exists('view/theme/' . $t . '/css/style.css') ||
                file_exists('view/theme/' . $t . '/php/style.php')
            ) {
                return (array($t));
            }
        }

        // Worst case scenario, the default base theme or themes don't exist; perhaps somebody renamed it/them.

        // Find any theme at all and use it.

        $fallback = array_merge(glob('view/theme/*/css/style.css'), glob('view/theme/*/php/style.php'));
        if (count($fallback)) {
            return (array(str_replace('view/theme/', '', substr($fallback[0], 0, -14))));
        }
    }


    /**
     * @brief Return full URL to theme which is currently in effect.
     *
     * Provide a sane default if nothing is chosen or the specified theme does not exist.
     *
     * @param bool $installing (optional) default false, if true return the name of the first base theme
     *
     * @return string
     */
    public static function url($installing = false)
    {

        if ($installing) {
            return self::$base_themes[0];
        }

        $theme = self::current();

        $t = $theme[0];
        $s = ((count($theme) > 1) ? $theme[1] : '');

        $opts = '';
        $opts = ((App::$profile_uid) ? '?f=&puid=' . App::$profile_uid : '');

        $schema_str = ((x(App::$layout, 'schema')) ? '&schema=' . App::$layout['schema'] : '');
        if (($s) && (!$schema_str)) {
            $schema_str = '&schema=' . $s;
        }

        $opts .= $schema_str;

        if (file_exists('view/theme/' . $t . '/php/style.php')) {
            return ('/view/theme/' . $t . '/php/style.pcss' . $opts);
        }

        return ('/view/theme/' . $t . '/css/style.css');
    }

    public static function include($file, $root = '')
    {

        // Make sure $root ends with a slash / if it's not blank
        if ($root) {
            $root = rtrim($root,'/') . '/';
        }
    
        $theme_info = App::$theme_info;

        if (array_key_exists('extends', $theme_info)) {
            $parent = $theme_info['extends'];
        } else {
            $parent = 'NOPATH';
        }

        $theme = self::current();
        $thname = $theme[0];

        $ext = substr($file, strrpos($file, '.') + 1);

        $paths = array(
            "{$root}view/theme/$thname/$ext/$file",
            "{$root}view/theme/$parent/$ext/$file",
            "{$root}view/site/$ext/$file",
            "{$root}view/$ext/$file",
        );

        foreach ($paths as $p) {

            if (strpos($p, 'NOPATH') !== false) {
                continue;
            }
            if (file_exists($p)) {
                return $p;
            }
        }

        return '';
    }

    public static function get_info($theme) {

        $info =  null;
        $has_yaml = true;
    
        if (is_file("view/theme/$theme.yml")) {
            $info = Infocon::from_file("view/theme/$theme.yml");
        }
        elseif (is_file("view/theme/$theme/php/theme.php")) {
            $has_yaml = false;
            $info = Infocon::from_c_comment("view/theme/$theme/php/theme.php");
        }

        if ($info && ! $has_yaml) {
            try {
                file_put_contents("view/theme/$theme.yml",Yaml::encode($info));
            }
            catch (Exception $e) {
            }
        }
    
        return $info ? $info : [ 'name' => $theme ] ;
    
    }

    public static function get_email_template($s, $root = '')
    {
            $testroot = ($root=='') ? $testroot = "ROOT" : $root;
            $t = App::template_engine();

        if (isset(App::$override_intltext_templates[$testroot][$s]["content"])) {
                return App::$override_intltext_templates[$testroot][$s]["content"];
        } else {
            if (isset(App::$override_intltext_templates[$testroot][$s]["root"]) &&
                   isset(App::$override_intltext_templates[$testroot][$s]["file"])) {
                    $s = App::$override_intltext_templates[$testroot][$s]["file"];
                    $root = App::$override_intltext_templates[$testroot][$s]["root"];
            } elseif (App::$override_templateroot) {
                $newroot = App::$override_templateroot.$root;
                if ($newroot != '' && substr($newroot, -1) != '/') {
                               $newroot .= '/';
                }
                $template = $t->get_email_template($s, $newroot);
            }
            $template = $t->get_email_template($s, $root);
            return $template;
        }
    }

    public static function get_template($s, $root = '')
    {
            $testroot = ($root=='') ? $testroot = "ROOT" : $root;

            $t = App::template_engine();

        if (isset(App::$override_markup_templates[$testroot][$s]["content"])) {
                return App::$override_markup_templates[$testroot][$s]["content"];
        } else {
            if (isset(App::$override_markup_templates[$testroot][$s]["root"]) &&
                   isset(App::$override_markup_templates[$testroot][$s]["file"])) {
                    $s = App::$override_markup_templates[$testroot][$s]["file"];
                    $root = App::$override_markup_templates[$testroot][$s]["root"];
            } elseif (App::$override_templateroot) {
                $newroot = App::$override_templateroot.$root;
                if ($newroot != '' && substr($newroot, -1) != '/') {
                               $newroot .= '/';
                }
                    $template = $t->get_template($s, $newroot);
            }
                $template = $t->get_template($s, $root);
                return $template;
        }
    }


    /**
     * @brief Returns the theme's screenshot.
     *
     * The screenshot is expected as view/theme/$theme/img/screenshot.[png|jpg].
     *
     * @param string $theme The name of the theme
     * @return string
     */
    public static function get_screenshot($theme)
    {

        $exts = array('.png', '.jpg');
        foreach ($exts as $ext) {
            if (file_exists('view/theme/' . $theme . '/img/screenshot' . $ext)) {
                return(z_root() . '/view/theme/' . $theme . '/img/screenshot' . $ext);
            }
        }

        return(z_root() . '/images/blank.png');
    }

    

    public static function debug()
    {
        logger('system_theme: ' . self::$system_theme);
        logger('session_theme: ' . self::$session_theme);
    }
}
