<?php
namespace Code\Lib;

use App;
use Code\Render\Theme;
        
class Head {

    /**
     * @brief add CSS to \<head\>
     *
     * @param string $src
     * @param string $media change media attribute (default to 'screen')
     */
    public static function add_css($src, $media = 'screen')
    {
        App::$css_sources[] = [ $src, $media ];
    }

    public static function remove_css($src, $media = 'screen')
    {

        $index = array_search([$src, $media], App::$css_sources);
        if ($index !== false) {
            unset(App::$css_sources[$index]);
        }
        // re-index the array
        App::$css_sources = array_values(App::$css_sources);
    }

    public static function get_css()
    {
        $str = EMPTY_STR;
        $sources = App::$css_sources;
        if (is_array($sources) && $sources) {
            foreach ($sources as $source) {
                $str .= self::format_css_if_exists($source);
            }
        }

        return $str;
    }

    public static function add_link($arr)
    {
        if ($arr) {
            App::$linkrel[] = $arr;
        }
    }

    public static function get_links()
    {
        $str = '';
        $sources = App::$linkrel;
        if (is_array($sources) && $sources) {
            foreach ($sources as $source) {
                if (is_array($source) && $source) {
                    $str .= '<link';
                    foreach ($source as $k => $v) {
                        $str .= ' ' . $k . '="' . $v . '"';
                    }
                    $str .= ' />' . "\r\n";
                }
            }
        }

        return $str;
    }

    public static function format_css_if_exists($source)
    {

        // script_path() returns https://yoursite.tld

        $path_prefix = self::script_path();

        $script = $source[0];

        if (strpos($script, '/') !== false) {
            // The script is a path relative to the server root
            $path = $script;
            // If the url starts with // then it's an absolute URL
            if (substr($script, 0, 2) === '//') {
                $path_prefix = '';
            }
        } else {
            // It's a file from the theme
            $path = '/' . Theme::include($script);
        }

        if ($path) {
            $qstring = ((parse_url($path, PHP_URL_QUERY)) ? '&' : '?') . 'v=' . STD_VERSION;
            return '<link rel="stylesheet" href="' . $path_prefix . $path . $qstring . '" type="text/css" media="' . $source[1] . '">' . "\r\n";
        }
    }

    /**
     * This basically calculates the baseurl. We have other functions to do that, but
     * there was an issue with script paths and mixed-content whose details are arcane
     * and perhaps lost in the message archives. The short answer is that we're ignoring
     * the URL which we are "supposed" to use, and generating script paths relative to
     * the URL which we are currently using; in order to ensure they are found and aren't
     * blocked due to mixed content issues.
     *
     * @return string
     */
    public static function script_path()
    {
        if (x($_SERVER, 'HTTPS') && $_SERVER['HTTPS']) {
            $scheme = 'https';
        } elseif (x($_SERVER, 'SERVER_PORT') && (intval($_SERVER['SERVER_PORT']) == 443)) {
            $scheme = 'https';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }

        // Some proxy setups may require using http_host

        if (isset(App::$config['system']['script_path_use_http_host']) && intval(App::$config['system']['script_path_use_http_host'])) {
            $server_var = 'HTTP_HOST';
        } else {
            $server_var = 'SERVER_NAME';
        }


        if (x($_SERVER, $server_var)) {
            $hostname = $_SERVER[$server_var];
        } else {
            return z_root();
        }

        return $scheme . '://' . $hostname;
    }

    public static function add_js($src, $priority = 0)
    {
        if (! (isset(App::$js_sources[$priority]) && is_array(App::$js_sources[$priority]))) {
            App::$js_sources[$priority] = [];
        }
        App::$js_sources[$priority][] = $src;
    }

    public static function remove_js($src, $priority = 0)
    {

        $index = array_search($src, App::$js_sources[$priority]);
        if ($index !== false) {
            unset(App::$js_sources[$priority][$index]);
        }
    }

    /**
     * We should probably try to register main.js with a high priority, but currently
     * we handle it separately and put it at the end of the html head block in case
     * any other javascript is added outside the head_add_js construct.
     *
     * @return string
     */
    public static function get_js()
    {

        $str = '';
        if (App::$js_sources) {
            ksort(App::$js_sources, SORT_NUMERIC);
            foreach (App::$js_sources as $sources) {
                if (count($sources)) {
                    foreach ($sources as $source) {
                        if ($source === 'main.js') {
                            continue;
                        }
                        $str .= self::format_js_if_exists($source);
                    }
                }
            }
        }

        return $str;
    }

    public static function get_main_js()
    {
        return self::format_js_if_exists('main.js', true);
    }

    public static function format_js_if_exists($source)
    {
        $path_prefix = self::script_path();

        if (strpos($source, '/') !== false) {
            // The source is a known path on the system
            $path = $source;
            // If the url starts with // then it's an absolute URL
            if (substr($source, 0, 2) === '//') {
                $path_prefix = '';
            }
        } else {
            // It's a file from the theme
            $path = '/' . Theme::include($source);
        }
        if ($path) {
            $qstring = ((parse_url($path, PHP_URL_QUERY)) ? '&' : '?') . 'v=' . STD_VERSION;
            return '<script src="' . $path_prefix . $path . $qstring . '" ></script>' . "\r\n" ;
        }
    }    

}