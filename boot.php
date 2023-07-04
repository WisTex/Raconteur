<?php

use Code\Lib\Libzot;
use Code\Storage\Stdio;
use Code\Web\Session;
use Code\Web\HttpMeta;
use Code\Render\SmartyTemplate;
use Code\Render\Comanche;
use Code\Render\Theme;
use Code\Lib\DB_Upgrade;
use Code\Lib\System;
use Code\Lib\PConfig;
use Code\Lib\Config;
use Code\Daemon\Run;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Lib\Stringsjs;
use Code\Extend\Hook;
use Code\Lib\Head;
use Code\Lib\Url;

/**
 * @file boot.php
 *
 * @brief This file defines some global constants and includes the central App class.
 */

const REPOSITORY_ID = 'raconteur';
const DB_UPDATE_VERSION = 1267;
const PROJECT_BASE = __DIR__;
const ACTIVITYPUB_ENABLED = true;
const NOMAD_PROTOCOL_VERSION = '11.0';

// composer autoloader for all namespaced Classes
require_once('vendor/autoload.php');
if (file_exists('addon/vendor/autoload.php')) {
    /** @noinspection PhpIncludeInspection */
    require_once('addon/vendor/autoload.php');
}

require_once('version.php');
if (file_exists('addon/version.php')) {
    require_once('addon/version.php');
}

require_once('include/constants.php');
require_once('include/config.php');
require_once('include/network.php');
require_once('include/misc.php');
require_once('include/datetime.php');
require_once('include/language.php');
require_once('include/permissions.php');
require_once('include/taxonomy.php');
require_once('include/connections.php');
require_once('include/zid.php');
require_once('include/xchan.php');
require_once('include/hubloc.php');
require_once('include/attach.php');
require_once('include/bbcode.php');
require_once('include/items.php');
require_once('include/dba/dba_driver.php');

function sys_boot() {

    // Optional startup file for situations which require system
    // configuration before anything is executed.
    if (file_exists('.htstartup.php')) {
        /** @noinspection PhpIncludeInspection */
        include('.htstartup.php');
    }

    if(!file_exists('.htaccess')) {
        Stdio::fcopy('htaccess.dist', '.htaccess');
    }

    // our central App object
    App::init();

    /*
     * Load the configuration file which contains our DB credentials.
     * Ignore errors. If the file doesn't exist or is empty, we are running in
     * installation mode.
     */

    App::$install = !((file_exists('.htconfig.php') && filesize('.htconfig.php')));

    $db_host = $db_user = $db_pass = $db_data = EMPTY_STR;
    $db_port = $db_type = 0;

    @include('.htconfig.php');

    date_default_timezone_set(App::$config['system']['timezone'] ?: 'UTC');

    /*
     * Try to open the database;
     */



    if (! App::$install) {
        DBA::dba_factory($db_host, $db_port, $db_user, $db_pass, $db_data, $db_type, App::$install);
        if (! DBA::$dba->connected) {
            system_unavailable();
        }

        unset($db_host, $db_port, $db_user, $db_pass, $db_data, $db_type);

        /*
         * Load configs from db. Overwrite configs from .htconfig.php
         */

        load_config('system');
        load_config('feature');

        App::$session = new Session();
        App::$session->init();
        App::$sys_channel = Channel::get_system();

        Hook::load();
        /**
         * @hooks 'startup'
         */
        $arr = [];
        Hook::call('startup',$arr);
    }
}


function startup() {
    error_reporting(E_ERROR | E_PARSE);

    // Some hosting providers block/disable this
    @set_time_limit(0);

    if (function_exists ('ini_set')) {
        // This has to be quite large
        @ini_set('pcre.backtrack_limit', 5000000);

        // Use cookies to store the session ID on the client side
        @ini_set('session.use_only_cookies', 1);

        // Disable transparent Session ID support
        @ini_set('session.use_trans_sid',    0);
    }
}


/**
 * class: App
 *
 * @brief Our main application structure for the life of this page.
 *
 * Primarily deals with the URL that got us here
 * and tries to make some sense of it, and
 * stores our page contents and config storage
 * and anything else that might need to be passed around
 * before we spit the page out.
 *
 */
class App {

    public  static $install    = false;           // true if we are installing the software
    public  static $account    = null;            // account record of the logged-in account
    public  static $channel    = null;            // channel record of the current channel of the logged-in account
    public  static $observer   = null;            // xchan record of the page observer
    public  static $profile_uid = 0;              // If applicable, the channel_id of the "page owner"
    public  static $sys_channel = null;           // cache sys channel lookups here
    public  static $poi        = null;            // "person of interest", generally a referenced connection or directory entry
    private static $oauth_key  = null;            // consumer_id of oauth request, if used
    public  static $layout     = [];              // Comanche parsed template
    public  static $pdl        = null;            // Comanche page description
    private static $perms      = null;            // observer permissions
    private static $widgets    = [];              // widgets for this page
    public  static $config     = [];              // config cache
    public  static $icon       = '';

    public  static $override_intltext_templates = [];
    public  static $override_markup_templates = [];
    public  static $override_templateroot = null;

    public static  $session    = null;
    public static  $language;
    public static  $langsave;
    public static  $rtl = false;
    public static  $addons_admin;
    public static  $module_loaded = false;
    public static  $query_string;
    public static  $page;
    public static  $profile;
    public static  $user;
    public static  $cid;
    public static  $contact;
    public static  $contacts;
    public static  $content;
    public static  $data = [];
    public static  $error = false;
    public static  $cmd;
    public static  $argv;
    public static  $argc;
    public static  $module;
    public static  $pager;
    public static  $strings;
    public static  $stringsave;   // used in push_lang() and pop_lang()
    public static  $hooks;
    public static  $interactive = true;
    public static  $addons;
    public static  $identities;
    public static  $css_sources = [];
    public static  $js_sources = [];
    public static  $linkrel = [];
    public static  $theme_info = [];
    public static  $is_sys = false;
    public static  $nav_sel;
    public static  $comanche;
    public static  $httpheaders = null;
    public static  $httpsig = null;
    public static  $channel_links;
    public static  $category;

    // Allow themes to control internal parameters
    // by changing App values in theme.php

    public static  $sourcename = '';
    public static  $videowidth = 425;
    public static  $videoheight = 350;
    public static $meta;

    /**
     * @brief An array for all theme-controllable parameters
     *
     * Mostly unimplemented yet. Only options 'template_engine' and
     * beyond are used.
     */
    private static $theme = [
        'sourcename'      => '',
        'videowidth'      => 425,
        'videoheight'     => 350,
        'force_max_items' => 0,
        'thread_allow'    => true,
        'stylesheet'      => '',
        'template_engine' => 'smarty3',
    ];

    /**
     * @brief An array of registered template engines ('name'=>'class name')
     */
    public static $template_engines = [];
    /**
     * @brief An array of instanced template engines ('name'=>'instance')
     */
    public static $template_engine_instance = [];

    private static $ldelim = [
        'internal' => '',
        'smarty3' => '{{'
    ];
    private static $rdelim = [
        'internal' => '',
        'smarty3' => '}}'
    ];

    // These represent the URL which was used to access the page

    private static $scheme;
    private static $hostname;
    private static $path;

    // This is our standardised URL - regardless of what was used
    // to access the page

    private static $baseurl;

    /**
     * App constructor.
     */

    public static function init() {
        // we'll reset this after we read our config file
        date_default_timezone_set('UTC');

        self::$config = [
            'system' => []
        ];
        self::$page = [];
        self::$pager= [];

        self::$query_string = '';

        startup();

        set_include_path(
            'include' . PATH_SEPARATOR
            . 'library' . PATH_SEPARATOR
            . '.'
        );

        // normally self::$hostname (also scheme and port) will be filled in during startup.
        // Set it manually from $_SERVER variables only if it wasn't.

        if (! self::$hostname) {
            self::$hostname = punify(get_host());
            self::$scheme = 'http';

            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
                self::$scheme = 'https';
            }
            elseif (isset($_SERVER['SERVER_PORT']) && (intval($_SERVER['SERVER_PORT']) === 443)) {
                self::$scheme = 'https';
            }

            if (isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) !== 80 && intval($_SERVER['SERVER_PORT']) !== 443) {
                self::$hostname .= ':' . $_SERVER['SERVER_PORT'];
            }
        }

        /*
         * Figure out if we are running at the top of a domain
         * or in a subdirectory and adjust accordingly
         */
        $path = trim(dirname($_SERVER['SCRIPT_NAME']),'/\\');
        if (strlen($path) && ($path != self::$path)) {
            self::$path = $path;
        }

        // Rewrite rules on the server will convert incoming paths to a request parameter.
        // Strip this path information from our stored copy of the query_string, in case
        // we need to re-use the rest of the original query.

        if (isset($_SERVER['QUERY_STRING']) && str_starts_with($_SERVER['QUERY_STRING'], "req=")) {
            self::$query_string = str_replace(['<','>'],['&lt;','&gt;'],substr($_SERVER['QUERY_STRING'], 4));
            // removing leading '/' - maybe a nginx problem
            if (str_starts_with(self::$query_string, "/")) {
                self::$query_string = substr(self::$query_string, 1);
            }
            // change the first & to ?
            self::$query_string = preg_replace('/&/','?',self::$query_string,1);
        }

        // Here is where start breaking out the URL path information to both route the
        // web request based on the leading path component, and also to use remaining
        // path components as C-style arguments to our individual controller modules.

        if (isset($_GET['req'])) {
            self::$cmd = escape_tags(trim($_GET['req'],'/\\'));
        }

        // support both unix and fediverse style "homedir"

        if ((str_starts_with(self::$cmd, '~')) || (str_starts_with(self::$cmd, '@'))) {
            self::$cmd = 'channel/' . substr(self::$cmd, 1);
        }

        /*
         * Break the URL path into C style argc/argv style arguments for our
         * modules. Given "http://example.com/module/arg1/arg2", self::$argc
         * will be 3 (integer) and self::$argv will contain:
         *   [0] => 'module'
         *   [1] => 'arg1'
         *   [2] => 'arg2'
         *
         * There will always be one argument. If provided a naked domain
         * URL, self::$argv[0] is set to "home".
         *
         * If $argv[0] has a period in it, for example foo.json; rewrite
         * to module = 'foo' and set $_REQUEST['module_format'] = 'json';
         *
         * As a result, say you offered a feed for member bob. Most applications
         * would address it as /feed/bob.xml or /feed/bob.json
         * We would address it as /feed.xml/bob  and /feed.json/bob because
         * you're altering the output format of the feed module, and bob is
         * just an identifier or variable.
         */

        self::$argv = explode('/', self::$cmd);

        self::$argc = count(self::$argv);
        if ((array_key_exists('0', self::$argv)) && strlen(self::$argv[0])) {
            if(strpos(self::$argv[0],'.')) {
                $_REQUEST['module_format'] = substr(self::$argv[0],strpos(self::$argv[0],'.')+1);
                self::$argv[0] =  substr(self::$argv[0],0,strpos(self::$argv[0],'.'));
            }

            self::$module = str_replace(".", "_", self::$argv[0]);
            self::$module = str_replace("-", "_", self::$module);
            if(str_starts_with(self::$module, '_'))
                self::$module = substr(self::$module,1);
        } else {
            self::$argc = 1;
            self::$argv = ['home'];
            self::$module = 'home';
        }


        /*
         * See if there is any page number information, and initialise
         * pagination
         */

        self::$pager['unset']     = !array_key_exists('page', $_REQUEST);
        self::$pager['page']      = ((x($_GET,'page') && intval($_GET['page']) > 0) ? intval($_GET['page']) : 1);
        self::$pager['itemspage'] = 60;
        self::$pager['start']     = (self::$pager['page'] * self::$pager['itemspage']) - self::$pager['itemspage'];
        self::$pager['total']     = 0;

        if (self::$pager['start'] < 0) {
            self::$pager['start'] = 0;
        }

        self::$meta = new HttpMeta();

        /*
         * register template engines (probably just smarty, but this can be extended)
         */

        self::register_template_engine(get_class(new SmartyTemplate()));

    }

    public static function get_baseurl() {
        if(is_array(self::$config)
            && array_key_exists('system',self::$config)
            && is_array(self::$config['system'])
            && array_key_exists('baseurl',self::$config['system'])
            && strlen(self::$config['system']['baseurl'])) {
            $url = self::$config['system']['baseurl'];
            return trim($url,'\\/');
        }

        $scheme = self::$scheme;

        self::$baseurl = $scheme . "://" . punify(self::$hostname) . ((isset(self::$path) && strlen(self::$path)) ? '/' . self::$path : '' );

        return self::$baseurl;
    }

    public static function set_baseurl($url) {
        if(is_array(self::$config)
            && array_key_exists('system',self::$config)
            && is_array(self::$config['system'])
            && array_key_exists('baseurl',self::$config['system'])
            && strlen(self::$config['system']['baseurl'])) {
            $url = punify(self::$config['system']['baseurl']);
            $url = trim($url,'\\/');
        }

        $parsed = @parse_url($url);

        self::$baseurl = $url;

        if($parsed !== false) {
            self::$scheme = $parsed['scheme'];

            self::$hostname = punify($parsed['host']);
            if(x($parsed,'port'))
                self::$hostname .= ':' . $parsed['port'];
            if(x($parsed,'path'))
                self::$path = trim($parsed['path'],'\\/');
        }
    }

    public static function get_scheme() {
        return self::$scheme;
    }

    public static function get_hostname() {
        return self::$hostname;
    }

    public static function set_hostname($h) {
        self::$hostname = $h;
    }

    public static function set_path($p) {
        self::$path = trim(trim($p), '/');
    }

    public static function get_path() {
        return self::$path;
    }

    public static function get_channel_links_header() {
        $s = '';
        $x = self::$channel_links;
        if ($x && is_array($x) && count($x)) {
            foreach ($x as $y) {
                if ($s) {
                    $s .= ',';
                }
                $s .= '<' . $y['href'] . '>; rel="' . $y['rel'] . '"; type="' . $y['type'] . '"';
            }
        }
        return $s;
    }

    public static function set_account($acct) {
        self::$account = $acct;
    }

    public static function get_account() {
        return self::$account;
    }

    public static function set_channel($channel) {
        self::$channel = $channel;
    }

    public static function get_channel(): mixed
    {
        return self::$channel;
    }

    public static function set_observer($xchan) {
        self::$observer = $xchan;
    }


    public static function get_observer(): mixed
    {
        return self::$observer;
    }

    public static function set_perms($perms) {
        self::$perms = $perms;
    }

    public static function get_perms() {
        return self::$perms;
    }

    public static function set_oauth_key($consumer_id) {
        self::$oauth_key = $consumer_id;
    }

    public static function get_oauth_key() {
        return self::$oauth_key;
    }


    public static function set_pager_total($n) {
        self::$pager['total'] = intval($n);
    }

    public static function set_pager_itemspage($n) {
        self::$pager['itemspage'] = ((intval($n) > 0) ? intval($n) : 0);
        self::$pager['start'] = (self::$pager['page'] * self::$pager['itemspage']) - self::$pager['itemspage'];
    }

    public static function build_pagehead() {

        $user_scalable = ((local_channel()) ? get_pconfig(local_channel(),'system','user_scalable', 0) : 0);

        $preload_images = ((local_channel()) ? get_pconfig(local_channel(),'system','preload_images',0) : 0);


        $interval = ((local_channel()) ? get_pconfig(local_channel(),'system','update_interval', 30000) : 30000);
        if ($interval < 15000) {
            $interval = 15000;
        }

        $alerts_interval = intval(get_config('system','alerts_interval',10000));
        if ($alerts_interval < 5000) {
            $alerts_interval = 5000;
        }

        if (! x(self::$page,'title')) {
            self::$page['title'] = ucfirst(App::$module) . ' | ' . ((array_path_exists('system/sitename',self::$config)) ? self::$config['system']['sitename'] : REPOSITORY_ID);
        }

        if (! self::$meta->get_field('og:title')) {
            self::$meta->set('og:title',self::$page['title']);
        }

        // webmanifest

        Head::add_link( [ 'rel' => 'manifest', 'href' => z_root() . '/manifest.webmanifest' ] );
        self::$meta->set('application-name', System::get_project_name() );
        self::$meta->set('generator', System::get_project_name());

        $i = head_get_icon();
        if (! $i) {
            $i = System::get_site_icon();
        }
        if ($i) {
            // normalise the sizes if possible.
            $i = str_replace('/s/','/l/',$i);
            $i = str_replace('/m/','/l/',$i);
            Head::add_link(['rel' => 'shortcut icon', 'href' => str_replace('/l/','/32/',$i) ]);
            Head::add_link(['rel' => 'icon', 'sizes' => '64x64', 'href' => str_replace('/l/','/64/', $i) ]);
            Head::add_link(['rel' => 'icon', 'sizes' => '192x192', 'href' => str_replace('/l/','/192/', $i) ]);
        }

        $x = [ 'header' => '' ];
        /**
         * @hooks build_pagehead
         *   Called when creating the HTML page header.
         *   * \e string \b header - Return the HTML header which should be added
         */
        Hook::call('build_pagehead', $x);

        /* put the head template at the beginning of page['htmlhead']
         * since the code added by the modules frequently depends on it
         * being first
         */

        if (! isset(self::$page['htmlhead'])) {
            self::$page['htmlhead'] = EMPTY_STR; // needed to silence warning
        }

        self::$page['htmlhead'] = replace_macros(Theme::get_template('head.tpl'),
            [
                '$preload_images'  => $preload_images,
                '$user_scalable'   => $user_scalable,
                '$query'           => urlencode(self::$query_string),
                '$baseurl'         => self::get_baseurl(),
                '$local_channel'   => local_channel(),
                '$metas'           => self::$meta->get(),
                '$plugins'         => $x['header'],
                '$update_interval' => $interval,
                '$alerts_interval' => $alerts_interval,
                '$head_css'        => Head::get_css(),
                '$head_js'         => Head::get_js(),
                '$linkrel'         => Head::get_links(),
                '$js_strings'      => Stringsjs::strings(),
                '$zid'             => Channel::get_my_address(),
                '$channel_id'      => ((isset(self::$profile) && is_array(self::$profile) && array_key_exists('uid',self::$profile)) ? self::$profile['uid'] : '')
            ]
        ) . self::$page['htmlhead'];

        // always put main.js at the end
        self::$page['htmlhead'] .= Head::get_main_js();
    }

    /**
    * @brief Register template engine class.
    *
    * If $name is "", is used class static property $class::$name.
    *
    * @param string $class
    * @param string $name
    */
    public static function register_template_engine($class, $name = '') {
        if (! $name) {
            $v = get_class_vars($class);
            if (x($v, "name")) {
                $name = $v['name'];
            }
        }
        if (! $name) {
            echo "template engine <b>$class</b> cannot be registered without a name.\n";
            killme();
        }
        self::$template_engines[$name] = $class;
    }

    /**
    * @brief Return template engine instance.
    *
    * If $name is not defined, return engine defined by theme, or default.
    *
    * @param string $name Template engine name
    *
    * @return mixed
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    public static function template_engine($name = '') {
        if ($name !== '') {
            $template_engine = $name;
        }
        else {
            $template_engine = 'smarty3';
            if (x(self::$theme, 'template_engine')) {
                $template_engine = self::$theme['template_engine'];
            }
        }

        if (isset(self::$template_engines[$template_engine])) {
            if (isset(self::$template_engine_instance[$template_engine])) {
                return self::$template_engine_instance[$template_engine];
            }
            else {
                $class = self::$template_engines[$template_engine];
                $obj = new $class();
                self::$template_engine_instance[$template_engine] = $obj;
                return $obj;
            }
        }

        // If we fell through to this step, it is considered fatal.

        echo "template engine <b>$template_engine</b> is not registered!\n";
        killme();
    }

    /**
     * @brief Returns the active template engine.
     *
     * @return string
     */
    public static function get_template_engine() {
        return self::$theme['template_engine'];
    }

    public static function set_template_engine($engine = 'smarty3') {
        self::$theme['template_engine'] = $engine;
    }

    public static function get_template_ldelim($engine = 'smarty3') {
        return self::$ldelim[$engine];
    }

    public static function get_template_rdelim($engine = 'smarty3') {
        return self::$rdelim[$engine];
    }

    public static function head_set_icon($icon) {
        self::$icon = $icon;
    }

    public static function head_get_icon() {
        $icon = self::$icon;
        if ($icon && ! strpos($icon,'://')) {
            $icon = z_root() . $icon;
        }
        return $icon;
    }

} // End App class


/**
 * @brief Multipurpose function to check variable state.
 *
 * Usage: x($var) or x($array, 'key')
 *
 * returns false if variable/key is not set
 * if variable is set, returns 1 if variable contains 'truthy' value, otherwise returns 0.
 * e.g. x('') or x(0) returns 0;
 *
 * @param string|array $s variable to check
 * @param string $k key inside the array to check
 *
 * @return bool|int
 */
function x($s, $k = null) {
    if($k != null) {
        if((is_array($s)) && (array_key_exists($k, $s))) {
            if($s[$k])
                return (int) 1;
            return (int) 0;
        }
        return false;
    }
    else {
        if(isset($s)) {
            if($s) {
                return (int) 1;
            }
            return (int) 0;
        }
        return false;
    }
}


/**
 * @brief Called from db initialisation if db is dead.
 *
 * @ref include/system_unavailable.php will handle everything further.
 */
function system_unavailable() {
    include('include/system_unavailable.php');
    system_down();
    killme();
}


function clean_urls() {

    //  if(App::$config['system']['clean_urls'])
    return true;
    //  return false;
}

function z_path() {
    $base = z_root();
    if(! clean_urls())
        $base .= '/?q=';

    return $base;
}

/**
 * @brief Returns the baseurl.
 *
 * @see App::get_baseurl()
 *
 * @return string
 */
function z_root() {
    return App::get_baseurl();
}

/**
 * @brief Return absolute URL for given $path.
 *
 * @param string $path
 *
 * @return string
 */
function absurl($path) {
    if (str_starts_with($path, '/')) {
        return z_path() . $path;
    }

    return $path;
}

/**
 * @brief Recursively delete a directory.
 *
 * @param string $path
 * @return bool
 */
function rrmdir($path) {
    if (is_dir($path) === true) {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            rrmdir(realpath($path) . '/' . $file);
        }
        return rmdir($path);
    }
    elseif (is_file($path) === true) {
        return unlink($path);
    }

    return false;
}


/**
 * @brief Function to check if request was an AJAX (xmlhttprequest) request.
 *
 * @return bool
 */
function is_ajax() {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
}

function killme_if_ajax() {
    if (is_ajax()) {
        killme();
    }
}

/**
 * Primarily involved with database upgrade, but also sets the
 * base url for use in cmdline programs which don't have
 * $_SERVER variables, and synchronising the state of installed plugins.
 */
function check_config() {

    $saved = get_config('system','urlverify');
    if (! $saved) {
        set_config('system','urlverify', bin2hex(z_root()));
    }

    if(($saved) && ($saved != bin2hex(z_root()))) {
        // our URL changed. Do something.

        $oldurl = hex2bin($saved);
        logger('Baseurl changed!');

        $oldhost = substr($oldurl, strpos($oldurl, '//') + 2);
        $host = substr(z_root(), strpos(z_root(), '//') + 2);

        $is_ip_addr = (bool)preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/", $host);
        $was_ip_addr = (bool)preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/", $oldhost);
        // only change the url to an ip address if it was already an ip and not a dns name
        if((! $is_ip_addr) || ($is_ip_addr && $was_ip_addr)) {
            fix_system_urls($oldurl,z_root());
            set_config('system', 'urlverify', bin2hex(z_root()));
        }
        else
            logger('Attempt to change baseurl from a DNS name to an IP address was refused.');
    }

    // This will actually set the url to the one stored in .htconfig, and ignore what
    // we're passing - unless we are installing, and it has never been set.

    App::set_baseurl(z_root());

    // Ensure the site has a system channel and that it has been upgraded.
    // This function will only do work if work is required.

    Channel::create_system();

    /** @noinspection PhpUnusedLocalVariableInspection */
    $x = new DB_Upgrade(DB_UPDATE_VERSION);

    Hook::load();

    check_cron_broken();

}


function fix_system_urls($oldurl, $newurl) {


    logger('fix_system_urls: renaming ' . $oldurl . '  to ' . $newurl);

    // Basically a site rename, but this can happen if you change from http to https for instance - even if the site name didn't change
    // This should fix URL changes on our site, but other sites will end up with orphan hublocs which they will try to contact and will
    // cause wasted communications.
    // What we need to do after fixing this up is to send a revocation of the old URL to every other site that we communicate with so
    // that they can clean up their hubloc tables (this includes directories).
    // It's a very expensive operation, so you don't want to have to do it often or after your site gets to be large.

    $r = q("select xchan.*, hubloc.* from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url like '%s' and hublod_deleted = 0",
        dbesc($oldurl . '%')
    );

    if($r) {
        foreach($r as $rv) {
            $channel_address = substr($rv['hubloc_addr'],0,strpos($rv['hubloc_addr'],'@'));

            // get the associated channel. If we don't have a local channel, do nothing for this entry.

            $c = q("select * from channel where channel_hash = '%s' limit 1",
                dbesc($rv['hubloc_hash'])
            );
            if(! $c)
                continue;

            $parsed = @parse_url($newurl);
            if(! $parsed)
                continue;
            $newhost = $parsed['host'];

            // sometimes parse_url returns unexpected results.

            if(str_contains($newhost, '/'))
                $newhost = substr($newhost,0,strpos($newhost,'/'));

            $rhs = $newhost . (($parsed['port']) ? ':' . $parsed['port'] : '');

            // paths aren't going to work. You have to be at the (sub)domain root
            // . (($parsed['path']) ? $parsed['path'] : '');

            // The xchan_url might point to another nomadic identity clone

            $replace_xchan_url = str_contains($rv['xchan_url'], $oldurl);

            q("update xchan set xchan_addr = '%s', xchan_url = '%s', xchan_connurl = '%s', xchan_follow = '%s', xchan_connpage = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_date = '%s' where xchan_hash = '%s'",
                dbesc($channel_address . '@' . $rhs),
                dbesc(($replace_xchan_url) ? str_replace($oldurl,$newurl,$rv['xchan_url']) : $rv['xchan_url']),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_connurl'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_follow'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_connpage'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_l'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_m'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_s'])),
                dbesc(datetime_convert()),
                dbesc($rv['xchan_hash'])
            );


            q("update hubloc set hubloc_addr = '%s', hubloc_url = '%s', hubloc_id_url = '%s', hubloc_url_sig = '%s', hubloc_site_id = '%s', hubloc_host = '%s', hubloc_callback = '%s' where hubloc_hash = '%s' and hubloc_url = '%s'",
                dbesc($channel_address . '@' . $rhs),
                dbesc($newurl),
                dbesc(str_replace($oldurl,$newurl,$rv['hubloc_id_url'])),
                dbesc(Libzot::sign($newurl,$c[0]['channel_prvkey'])),
                dbesc(Libzot::make_xchan_hash($newurl,Config::Get('system','pubkey'))),
                dbesc($newhost),
                dbesc($newurl . '/post'),
                dbesc($rv['xchan_hash']),
                dbesc($oldurl)
            );

            q("update profile set photo = '%s', thumb = '%s' where uid = %d",
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_l'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_m'])),
                intval($c[0]['channel_id'])
            );

            $m = q("select abook_id, abook_instance from abook where abook_instance like '%s' and abook_channel = %d",
                dbesc('%' . $oldurl . '%'),
                intval($c[0]['channel_id'])
            );
            if($m) {
                foreach($m as $mm) {
                    q("update abook set abook_instance = '%s' where abook_id = %d",
                        dbesc(str_replace($oldurl,$newurl,$mm['abook_instance'])),
                        intval($mm['abook_id'])
                    );
                }
            }

            Run::Summon( [ 'Notifier', 'refresh_all', $c[0]['channel_id'] ]);
        }
    }


    // fix links in apps

    $a = q("select id, app_url, app_photo from app where app_url like '%s' OR app_photo like '%s'",
        dbesc('%' . $oldurl . '%'),
        dbesc('%' . $oldurl . '%')
    );
    if($a) {
        foreach($a as $aa) {
            q("update app set app_url = '%s', app_photo = '%s' where id = %d",
                dbesc(str_replace($oldurl,$newurl,$aa['app_url'])),
                dbesc(str_replace($oldurl,$newurl,$aa['app_photo'])),
                intval($aa['id'])
            );
        }
    }

    // now replace any remote xchans whose photos are stored locally (which will be most if not all remote xchans)

    $r = q("select * from xchan where xchan_photo_l like '%s'",
        dbesc($oldurl . '%')
    );

    if($r) {
        foreach($r as $rv) {
            $x = q("update xchan set xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s' where xchan_hash = '%s'",
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_l'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_m'])),
                dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_s'])),
                dbesc($rv['xchan_hash'])
            );
        }
    }
}


/**
 * @brief Wrapper for adding a login box.
 *
 * If $register == true provide a registration link. This will most always depend
 * on the value of App::$config['system']['register_policy'].
 * Returns the complete html for inserting into the page
 *
 * @param bool $register (optional) default false
 * @param string $form_id (optional) default \e main-login
 * @param bool $hiddens (optional) default false
 * @param bool $login_page (optional) default true
 * @return string Parsed HTML code.
 */
function login($register = false, $form_id = 'main-login', $hiddens = false, $login_page = true) {

    $o = '';
    $reg = null;

    // Here's the current description of how the register link works (2018-05-15)

    // Register links are enabled on the site home page and login page and navbar.
    // They are not shown by default on other pages which may require login.

    // system.register_link can over-ride the default behaviour and redirect to an arbitrary
    // webpage for paid/custom or organisational registrations, regardless of whether
    // registration is allowed.

    // system.register_link may or may not be the same destination as system.sellpage

    // system.sellpage is the destination linked from the /pubsites page on other sites. If
    // system.sellpage is not set, the 'register' link in /pubsites will go to 'register' on your
    // site.

    // If system.register_link is set to the word 'none', no registration link will be shown on
    // your site.


    // If the site supports SSL and this isn't a secure connection, reload the page using https

    if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') && str_contains(z_root(), 'https://')) {
        goaway(z_root() . '/' . App::$query_string);
    }

    $register_policy = get_config('system','register_policy');

    $reglink = get_config('system', 'register_link', z_root() . '/register');

    if($reglink !== 'none') {
        $reg = [
            'title' => t('Create an account to access services and applications'),
            'desc'  => t('Register'),
            'link'  => $reglink
        ];
    }

    $dest_url = z_root() . '/' . App::$query_string;

    if(local_channel()) {
        $tpl = Theme::get_template('logout.tpl');
    }
    else {
        $tpl = Theme::get_template('login.tpl');
        if(strlen(App::$query_string))
            $_SESSION['login_return_url'] = App::$query_string;
    }

    $o .= replace_macros($tpl, [
        '$dest_url'     => $dest_url,
        '$login_page'   => $login_page,
        '$logout'       => t('Logout'),
        '$login'        => t('Login'),
        '$remote_login' => t('Remote Authentication'),
        '$form_id'      => $form_id,
        '$lname'        => [ 'username', t('Login/Email') , '', '' ],
        '$lpassword'    => [ 'password', t('Password'), '', '' ],
        '$remember_me'  => [ (($login_page) ? 'remember' : 'remember_me'), t('Remember me'), '', '', [ t('No'),t('Yes') ] ],
        '$hiddens'      => $hiddens,
        '$register'     => $reg,
        '$lostpass'     => t('Forgot your password?'),
        '$lostlink'     => t('Password Reset'),
    ]);

    /**
     * @hooks login_hook
     *   Called when generating the login form.
     *   * \e string with parsed HTML
     */
    Hook::call('login_hook', $o);

    return $o;
}


/**
 * @brief Used to end the current process, after saving session state.
 */
function killme() {

    register_shutdown_function('shutdown');
    exit;
}

/**
 * @brief Redirect to another URL and terminate this process.
 */
function goaway($s) {
    header("Location: $s");
    killme();
}

function shutdown() {

}

/**
 * @brief Returns the entity id of locally logged in account or false.
 *
 * Returns numeric account_id if authenticated or false. It is possible to be
 * authenticated and not connected to a channel.
 *
 * @return int|bool account_id or false
 */

function get_account_id() {

    if(isset($_SESSION['account_id']) && intval($_SESSION['account_id']))
        return intval($_SESSION['account_id']);

    if(App::$account)
        return intval(App::$account['account_id']);

    return false;
}

/**
 * @brief Returns the entity id (channel_id) of locally logged in channel or false.
 *
 * Returns authenticated numeric channel_id if authenticated and connected to
 * a channel or 0. Sometimes referred to as $uid in the code.
 *
 * Before 2.1 this function was called local_user().
 *
 * @since 2.1
 * @return int|bool channel_id or false
 */

function local_channel() {
    if(session_id()
        && array_key_exists('authenticated',$_SESSION) && $_SESSION['authenticated']
        && array_key_exists('uid',$_SESSION) && intval($_SESSION['uid']))
        return intval($_SESSION['uid']);

    return false;
}

/**
 * @brief Returns a xchan_hash (visitor_id) of remote authenticated visitor
 * or false.
 *
 * Returns authenticated string hash of Red global identifier (xchan_hash), if
 * authenticated via remote auth, or an empty string.
 *
 * Before 2.1 this function was called remote_user().
 *
 * @since 2.1
 * @return string|bool visitor_id or false
 */
function remote_channel() {
    if(session_id()
        && array_key_exists('authenticated',$_SESSION) && $_SESSION['authenticated']
        && array_key_exists('visitor_id',$_SESSION) && $_SESSION['visitor_id'])
        return $_SESSION['visitor_id'];

    return false;
}


function can_view_public_stream() {

    if (! (intval(get_config('system','open_pubstream',0)))) {
        if (! local_channel()) {
            return false;
        }
    }

    $public_stream_mode = intval(get_config('system','public_stream_mode',PUBLIC_STREAM_NONE));
    return (bool)$public_stream_mode;

}


/**
 * @brief Show an error or alert text on next page load.
 *
 * Contents of $s are displayed prominently on the page the next time
 * a page is loaded. Usually used for errors or alerts.
 *
 * For informational text use info().
 *
 * @param string $s Text to display
 */
function notice($s) {

    if (! session_id()) {
        return;
    }

    if (! x($_SESSION, 'sysmsg')) {
        $_SESSION['sysmsg'] = [];
    }

    // ignore duplicated error messages which haven't yet been displayed
    // - typically seen as multiple 'permission denied' messages
    // as a result of auto-reloading a protected page with &JS=1

    if (in_array($s, $_SESSION['sysmsg'])) {
        return;
    }

    if (App::$interactive) {
        $_SESSION['sysmsg'][] = $s;
    }
}

/**
 * @brief Show an information text on next page load.
 *
 * Contents of $s are displayed prominently on the page the next time a page is
 * loaded. Usually used for information.
 *
 * For error and alerts use notice().
 *
 * @param string $s Text to display
 */
function info($s) {
    if (! session_id()) {
        return;
    }

    if (! x($_SESSION, 'sysmsg_info')) {
        $_SESSION['sysmsg_info'] = [];
    }

    if (in_array($s, $_SESSION['sysmsg_info'])) {
        return;
    }

    if (App::$interactive) {
        $_SESSION['sysmsg_info'][] = $s;
    }
}

/**
 * @brief Wrapper around config to limit the text length of an incoming message.
 *
 * @return int
 */
function get_max_import_size() {
    return(intval(get_config('system', 'max_import_size')));
}


/**
 * @brief Wrap calls to proc_close(proc_open()) and call hook
 * so plugins can take part in process :)
 *
 * args:
 * $cmd program to run
 *  next args are passed as $cmd command line
 *
 * e.g.:
 * @code{.php}proc_run("ls", "-la", "/tmp");@endcode
 *
 * $cmd and string args are surrounded with ""
 */
function proc_run() {

    $args = func_get_args();

    if (! count($args))
        return;

    $args = flatten_array_recursive($args);

    $arr =  [
        'args' => $args,
        'run_cmd' => true
    ];

    /**
     * @hooks proc_run
     *   Called when invoking PHP sub processes.
     *   * \e array \b args
     *   * \e boolean \b run_cmd
     */

    Hook::call('proc_run', $arr);

    if (! $arr['run_cmd']) {
        return;
    }

    if (count($args) > 1  && $args[0] === 'php') {
        /** @noinspection PhpUnhandledExceptionInspection */
        $php = check_php_cli();
        if (! $php) {
            return;
        }
        $args[0] = $php;
    }

    $args = array_map('escapeshellarg',$args);
    $cmdline = implode(' ', $args);
    exec($cmdline . ' > /dev/null &');
}

function check_php_cli() {

    $cfg = (isset(App::$config['system']['php_path']))
        ? App::$config['system']['php_path']
        : NULL;

    if (isset($cfg) && is_executable($cfg)) {
        return $cfg;
    }

    $path = shell_exec('which php');
    if ($path && is_executable(trim($path))) {
        return trim($path);
    }

    logger('PHP command line interpreter not found.');
    /** @noinspection PhpUnhandledExceptionInspection */
    throw new Exception('interpreter not  found.');
}

/**
 * @brief Check if current user has admin role.
 *
 * Check if the current user has ACCOUNT_ROLE_ADMIN.
 *
 * @return bool true if user is an admin
 */
function is_site_admin() {

    if(! session_id())
        return false;

    if(isset($_SESSION['delegate']))
        return false;

    if(isset($_SESSION['authenticated']) && intval($_SESSION['authenticated'])) {
        if (is_array(App::$account) && (App::$account['account_roles'] & ACCOUNT_ROLE_ADMIN)) {
            return true;
        }
        // the system channel is by definition an administrator
        if (isset(App::$sys_channel) && is_array(App::$sys_channel) && array_key_exists('channel_id', (array) App::$sys_channel) && intval(App::$sys_channel['channel_id']) === local_channel()) {
            return true;
        }
    }

    return false;
}

/**
 * @brief Check if current user has developer role.
 *
 * Check if the current user has ACCOUNT_ROLE_DEVELOPER.
 *
 * @return bool true if user is a developer
 */
function is_developer() {

    if(! session_id())
        return false;

    if(isset($_SESSION['authenticated'])
        && (intval($_SESSION['authenticated']))
        && (is_array(App::$account))
        && (App::$account['account_roles'] & ACCOUNT_ROLE_DEVELOPER))
        return true;

    return false;
}


function load_contact_links($uid) {

    $ret = [];

    if (! $uid || x(App::$contacts,'empty')) 
        return;

//  logger('load_contact_links');

    $r = q("SELECT abook_id, abook_flags, abook_self, abook_incl, abook_excl, xchan_hash, xchan_photo_m, xchan_name, xchan_url, xchan_addr, xchan_network, xchan_type from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d ",
        intval($uid)
    );
    if ($r) {
        foreach ($r as $rv) {
            $ret[$rv['xchan_hash']] = $rv;
        }
    }
    else {
        $ret['empty'] = true;
    }
    App::$contacts = $ret;
}


/**
 * @brief Returns querystring as string from a mapped array.
 *
 * @param array $params mapped array with query parameters
 * @param string $name of parameter, default null
 *
 * @return string
 */
function build_querystring($params, $name = null) {
    $ret = '';
    foreach($params as $key => $val) {
        if(is_array($val)) {
            if($name === null) {
                $ret .= build_querystring($val, $key);
            } else {
                $ret .= build_querystring($val, $name . "[$key]");
            }
        } else {
            $val = urlencode($val);
            if($name != null) {
                $ret .= $name . "[$key]" . "=$val&";
            } else {
                $ret .= "$key=$val&";
            }
        }
    }

    return $ret;
}


/**
 * @brief Much better way of dealing with c-style args.
 */
function argc() {
    return App::$argc;
}

function argv($x) {
    if(array_key_exists($x,App::$argv))
        return App::$argv[$x];

    return '';
}

/**
 * @brief Returns xchan_hash from the observer.
 *
 * Observer can be a local or remote channel.
 *
 * @return string xchan_hash from observer, otherwise empty string if no observer
 */
function get_observer_hash() {
    $observer = App::get_observer();
    if (is_array($observer)) {
        return $observer['xchan_hash'];
    }
    return '';
}

/**
 * @brief Returns the complete URL of the current page, e.g.: http(s)://something.com/network
 *
 * Taken from http://webcheatsheet.com/php/get_current_page_url.php
 *
 * @return string
 */
function curPageURL() {
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") {
        $pageURL .= "s";
    }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }

    return $pageURL;
}

/**
 * @brief Returns a custom navigation by name???
 *
 * If no $navname provided load default page['nav']
 *
 * @todo not fully implemented yet
 *
 * @param string $navname
 *
 * @return mixed
 */
function get_custom_nav($navname) {
    if (! $navname)
        return App::$page['nav'];
    // load custom nav menu by name here
    return EMPTY_STR;
}

/**
 * @brief Loads a page definition file for a module.
 *
 * If there is no parsed Comanche template already load a module's pdl file
 * and parse it with Comanche.
 *
 */
function load_pdl() {

    App::$comanche = new Comanche();

    if (! count(App::$layout)) {

        $arr = [
            'module' => App::$module,
            'layout' => ''
        ];
        /**
         * @hooks load_pdl
         *   Called when we load a PDL file or description.
         *   * \e string \b module
         *   * \e string \b layout
         */
        Hook::call('load_pdl', $arr);
        $layout = $arr['layout'];

        $n = 'mod_' . App::$module . '.pdl' ;
        $u = App::$comanche->get_channel_id();
        if($u)
            $s = get_pconfig($u, 'system', $n);
        if(! (isset($s) && $s))
            $s = $layout;

        if((! $s) && (($p = Theme::include($n)) != ''))
            $s = @file_get_contents($p);
        elseif(file_exists('addon/'. App::$module . '/' . $n))
            $s = @file_get_contents('addon/'. App::$module . '/' . $n);

        $arr = [
            'module' => App::$module,
            'layout' => $s
        ];
        Hook::call('alter_pdl',$arr);
        $s = $arr['layout'];

        if($s) {
            App::$comanche->parse($s);
            App::$pdl = $s;
        }
    }
}


function exec_pdl() {
    if(App::$pdl) {
        App::$comanche->parse(App::$pdl,1);
    }
}


/**
 * @brief build the page.
 *
 * Build the page - now that we have all the components
 *
 */
function construct_page() {

    Hook::call('page_end', App::$page['content']);

    exec_pdl();

    $comanche = isset(App::$layout) && is_array(App::$layout) && count(App::$layout);

    require_once(Theme::include('theme_init.php'));

    $installing = false;

    $uid = ((App::$profile_uid) ?: local_channel());

    $navbar = get_config('system','navbar','default');
    if($uid) {
        $navbar = get_pconfig($uid,'system','navbar',$navbar);
    }

    if($comanche && isset(App::$layout['navbar'])) {
        $navbar = App::$layout['navbar'];
    }

    if (App::$module == 'setup') {
        $installing = true;
    }
    else {
        Navbar::render($navbar);
    }


    $current_theme = Theme::current();

    if (($p = Theme::include($current_theme[0] . '.js')) != '')
        Head::add_js('/' . $p);

    if (($p = Theme::include('mod_' . App::$module . '.php')) != '')
        require_once($p);

    if (isset(App::$page['template_style']))
        Head::add_css(App::$page['template_style'] . '.css');
    else
        Head::add_css(((isset(App::$page['template'])) ? App::$page['template'] : 'default' ) . '.css');

    if (($p = Theme::include('mod_' . App::$module . '.css')) != '')
        Head::add_css('mod_' . App::$module . '.css');

    Head::add_css(Theme::url($installing));

    if (($p = Theme::include('mod_' . App::$module . '.js')) != '')
        Head::add_js('mod_' . App::$module . '.js');

    App::build_pagehead();

    if (isset(App::$page['pdl_content']) && App::$page['pdl_content']) {
        App::$page['content'] = App::$comanche->region(App::$page['content']);
    }

    // Let's say we have a comanche declaration '[region=nav][/region][region=content]$nav $content[/region]'.
    // The text 'region=' identifies a section of the layout by that name. So what we want to do here is leave
    // App::$page['nav'] empty and put the default content from App::$page['nav'] and App::$page['section']
    // into a new region called App::$data['content']. It is presumed that the chosen layout file for this comanche page
    // has a '<content>' element instead of a '<section>'.

    // This way the Comanche layout can include any existing content, alter the layout by adding stuff around it or changing the
    // layout completely with a new layout definition, or replace/remove existing content.

    if ($comanche) {
        $arr = [
                'module' => App::$module,
                'layout' => App::$layout
        ];
        /**
         * @hooks construct_page
         *   General purpose hook to provide content to certain page regions.
         *   Called when constructing the Comanche page.
         *   * \e string \b module
         *   * \e string \b layout
         */
        Hook::call('construct_page', $arr);
        App::$layout = ((isset($arr['layout']) && is_array($arr['layout'])) ? $arr['layout'] : []);

        foreach(App::$layout as $k => $v) {
            if((str_starts_with($k, 'region_')) && strlen($v)) {
                if(str_contains($v, '$region_')) {
                    $v = preg_replace_callback('/\$region_([a-zA-Z0-9]+)/ism', [App::$comanche,'replace_region'], $v);
                }

                // And a couple of convenience macros
                if(str_contains($v, '$htmlhead')) {
                    $v = str_replace('$htmlhead', App::$page['htmlhead'], $v);
                }
                if(str_contains($v, '$nav')) {
                    $v = str_replace('$nav', App::$page['nav'], $v);
                }
                if(str_contains($v, '$content')) {
                    $v = str_replace('$content', App::$page['content'], $v);
                }

                App::$page[substr($k, 7)] = $v;
            }
        }
    }

    $page    = App::$page;
    $profile = App::$profile;

    // There's some experimental support for right-to-left text in the view/php/default.php page template.
    // In v1.9 we started providing direction preference in the per language hstrings.php file
    // This requires somebody with fluency in RTL languages to make happen

    $page['direction'] = 0; // ((App::$rtl) ? 1 : 0);

    header("Content-type: text/html; charset=utf-8");

    // security headers - see https://securityheaders.io

    if (App::get_scheme() === 'https' && isset(App::$config['system']['transport_security_header']) && App::$config['system']['transport_security_header']) {
        header("Strict-Transport-Security: max-age=31536000");
    }

    if (isset(App::$config['system']['content_security_policy']) && App::$config['system']['content_security_policy']) {
        $cspsettings = [
            'script-src' => ["'self'","'unsafe-inline'","'unsafe-eval'"],
            'style-src' => ["'self'","'unsafe-inline'"]
        ];
        Hook::call('content_security_policy',$cspsettings);

        // Legitimate CSP directives (cxref: https://content-security-policy.com/)
        $validcspdirectives= [
            "default-src", "script-src", "style-src",
            "img-src", "connect-src", "font-src",
            "object-src", "media-src", 'frame-src',
            'sandbox', 'report-uri', 'child-src',
            'form-action', 'frame-ancestors', 'plugin-types'
        ];
        $cspheader = "Content-Security-Policy:";
        foreach ($cspsettings as $cspdirective => $csp) {
            if (! in_array($cspdirective,$validcspdirectives)) {
                logger("INVALID CSP DIRECTIVE: ".$cspdirective,LOGGER_DEBUG);
                continue;
            }
            $cspsettingsarray = array_unique($csp);
            $cspsetpolicy = implode(' ',$cspsettingsarray);
            if ($cspsetpolicy) {
                $cspheader .= " $cspdirective $cspsetpolicy;";
            }
        }
        header($cspheader);
    }

    if (isset(App::$config['system']['x_security_headers']) && App::$config['system']['x-security_headers']) {
        header("X-Frame-Options: SAMEORIGIN");
        header("X-Xss-Protection: 1; mode=block;");
        header("X-Content-Type-Options: nosniff");
    }


    if (isset(App::$config['system']['perm_policy_header']) && App::$config['system']['perm_policy_header']) {
        header("Permissions-Policy: " . App::$config['system']['perm_policy_header']);
    }
    else {
        // opt-out this site from federated browser surveillance
        header("Permissions-Policy: interest-cohort=()");
    }

    if (isset(App::$config['system']['public_key_pins']) && App::$config['system']['public_key_pins']) {
        header("Public-Key-Pins: " . App::$config['system']['public_key_pins']);
    }

    require_once(Theme::include(
        ((isset(App::$page['template']) && App::$page['template']) ? App::$page['template'] : 'default' ) . '.php' )
    );
}

/**
 * @brief Set a pageicon.
 *
 * @param string $icon
 */
function head_set_icon($icon) {

    App::$icon = $icon;

}

/**
 * @brief Get the pageicon.
 *
 * @return string absolute path to pageicon
 */
function head_get_icon() {

    $icon = ((App::$icon) ?: EMPTY_STR);
    if($icon && ! strpos($icon, '://'))
        $icon = z_root() . $icon;

    return $icon;
}



/**
 * @brief Check if server certificate is valid.
 *
 * Notify admin if not.
 */
function z_check_cert() {
    if(str_contains(z_root(), 'https://')) {
        $x = Url::get(z_root() . '/api/z/1.0/version');
        if(! $x['success']) {
            $y = Url::get(z_root() . '/api/z/1.0/version', ['novalidate' => true]);
            if($y['success'])
                cert_bad_email();
        }
    }
}


/**
 * @brief Send email to admin if server has an invalid certificate.
 *
 * If a hub is available over https it must have a publicly valid certificate.
 */
function cert_bad_email() {
    return z_mail(
        [
            'toEmail'        => App::$config['system']['admin_email'],
            'messageSubject' => sprintf(t('[$Projectname] Website SSL error for %s'), App::get_hostname()),
            'textVersion'    => replace_macros(Theme::get_email_template('cert_bad_eml.tpl'),
                [
                    '$sitename' => App::$config['system']['sitename'],
                    '$siteurl'  => z_root(),
                    '$error'    => t('Website SSL certificate is not valid. Please correct.')
                ]
            )
        ]
    );

}



/**
 * @brief Send warnings every 3-5 days if cron is not running.
 */
function check_cron_broken() {

    $d = get_config('system', 'lastcron');

    if((! $d) || ($d < datetime_convert(datetime: 'now - 4 hours'))) {
        Run::Summon( [ 'Cron' ] );
        set_config('system', 'lastcron', datetime_convert());
    }

    $t = get_config('system', 'lastcroncheck');
    if(! $t) {
        // never checked before. Start the timer.
        set_config('system', 'lastcroncheck', datetime_convert());
        return true;
    }

    if($t > datetime_convert(datetime: 'now - 3 days')) {
        // Wait for 3 days before we do anything so as not to swamp the admin with messages
        return true;
    }

    set_config('system', 'lastcroncheck', datetime_convert());

    if(($d) && ($d > datetime_convert(datetime: 'now - 3 days'))) {
        // Scheduled tasks have run successfully in the last 3 days.
        return true;
    }

    return z_mail(
        [
            'toEmail'        => App::$config['system']['admin_email'],
            'messageSubject' => sprintf(t('[$Projectname] Cron tasks not running on %s'), App::get_hostname()),
            'textVersion'    => replace_macros(Theme::get_email_template('cron_bad_eml.tpl'),
                [
                    '$sitename' => App::$config['system']['sitename'],
                    '$siteurl'  =>  z_root(),
                    '$error'    => t('Cron/Scheduled tasks not running.'),
                    '$lastdate' => (($d)?: t('never'))
                ]
            )
        ]
    );
}

function get_safemode() {
    if (! array_key_exists('safemode', $_SESSION)) {
        $_SESSION['safemode'] = 1;
    }
    return intval($_SESSION['safemode']);
}

function supported_imagetype($x) {
    return in_array($x, [ IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ]);
}

function get_host() {
    if ($host = ((isset($_SERVER['HTTP_X_FORWARDED_HOST']) && $_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : EMPTY_STR)) {
        $elements = explode(',', $host);
        $host = trim(end($elements));
    }
    else {
        if (! $host = ((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : EMPTY_STR)) {
            if (! $host = ((isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : EMPTY_STR)) {
                $host = ((isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '');
            }
        }
    }

    // Remove port number from host
    if (str_contains($host, ':')) {
        $host = substr($host,0,strpos($host,':'));
    }
    return trim($host);
}

function get_loadtime($module) {
    $n  = 'loadtime_' . $module;
    if (isset($_SESSION[$n])) {
        return $_SESSION[$n];
    }
    if (local_channel()) {
        $x = PConfig::Get(local_channel(),'system', $n);
        if ($x) {
            return ($x);
        }
    }
    return datetime_convert();
}

