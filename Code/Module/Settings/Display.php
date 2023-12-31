<?php

namespace Code\Module\Settings;

use App;
use Code\Lib\Libsync;
use Code\Lib\Features;
use Code\Lib\Addon;
use Code\Extend\Hook;
use Code\Lib\PConfig;
use Code\Render\Theme;


class Display
{

    /*
     * DISPLAY SETTINGS
     */

    public function post()
    {
        check_form_security_token_redirectOnErr('/settings/display', 'settings_display');

        $themespec = explode(':', App::$channel['channel_theme']);
        $existing_theme = $themespec[0];
        $existing_schema = $themespec[1];

        $theme = ((x($_POST, 'theme')) ? notags(trim($_POST['theme'])) : $existing_theme);

        if (!$theme) {
            $theme = 'redbasic';
        }


        $preload_images = ((x($_POST, 'preload_images')) ? intval($_POST['preload_images']) : 0);
        $channel_menu = ((x($_POST, 'channel_menu')) ? intval($_POST['channel_menu']) : 0);
        $user_scalable = ((x($_POST, 'user_scalable')) ? intval($_POST['user_scalable']) : 0);
        $nosmile = ((x($_POST, 'nosmile')) ? intval($_POST['nosmile']) : 0);
        $filter_menu_open = ((x($_POST, 'filter_menu_open')) ? intval($_POST['filter_menu_open']) : 0);
        $indentpx = ((x($_POST, 'indentpx')) ? intval($_POST['indentpx']) : 0);

        $channel_divmore_height = ((x($_POST, 'channel_divmore_height')) ? intval($_POST['channel_divmore_height']) : 400);
        if ($channel_divmore_height < 50) {
            $channel_divmore_height = 50;
        }
        $stream_divmore_height = ((x($_POST, 'stream_divmore_height')) ? intval($_POST['stream_divmore_height']) : 400);
        if ($stream_divmore_height < 50) {
            $stream_divmore_height = 50;
        }

        $browser_update = ((x($_POST, 'browser_update')) ? intval($_POST['browser_update']) : 0);
        $browser_update = $browser_update * 1000;
        if ($browser_update < 15000) {
            $browser_update = 15000;
        }

        $itemspage = ((x($_POST, 'itemspage')) ? intval($_POST['itemspage']) : 20);
        if ($itemspage > 100) {
            $itemspage = 100;
        }

        if ($indentpx < 0) {
            $indentpx = 0;
        }
        if ($indentpx > 20) {
            $indentpx = 20;
        }

        set_pconfig(local_channel(), 'system', 'preload_images', $preload_images);
        set_pconfig(local_channel(), 'system', 'user_scalable', $user_scalable);
        set_pconfig(local_channel(), 'system', 'update_interval', $browser_update);
        set_pconfig(local_channel(), 'system', 'itemspage', $itemspage);
        set_pconfig(local_channel(), 'system', 'no_smilies', 1 - intval($nosmile));
        set_pconfig(local_channel(), 'system', 'channel_divmore_height', $channel_divmore_height);
        set_pconfig(local_channel(), 'system', 'stream_divmore_height', $stream_divmore_height);
        set_pconfig(local_channel(), 'system', 'channel_menu', $channel_menu);
        set_pconfig(local_channel(), 'system', 'thread_indent_px', $indentpx);
        set_pconfig(local_channel(), 'system', 'filter_menu_open', $filter_menu_open);

        $newschema = '';
        if ($theme) {
            // call theme_post only if theme has not been changed
            if (($themeconfigfile = $this->get_theme_config_file($theme)) != null) {
                require_once($themeconfigfile);
                if (class_exists('\\Code\\Theme\\' . ucfirst($theme) . 'Config')) {
                    $clsname = '\\Code\\Theme\\' . ucfirst($theme) . 'Config';
                    $theme_config = new $clsname();
                    $schemas = $theme_config->get_schemas();
                    if (array_key_exists($_POST['schema'], $schemas)) {
                        $newschema = $_POST['schema'];
                    }
                    if ($newschema === '---') {
                        $newschema = '';
                    }
                    $theme_config->post();
                }
            }
        }

        logger('theme: ' . $theme . (($newschema) ? ':' . $newschema : ''));

        $_SESSION['theme'] = $theme . (($newschema) ? ':' . $newschema : '');

        $r = q(
            "UPDATE channel SET channel_theme = '%s' WHERE channel_id = %d",
            dbesc($theme . (($newschema) ? ':' . $newschema : '')),
            intval(local_channel())
        );

        Hook::call('display_settings_post', $_POST);
        Libsync::build_sync_packet();
        goaway(z_root() . '/settings/display');
        return; // NOTREACHED
    }


    public function get()
    {

        $yes_no = [t('No'), t('Yes')];

        $default_theme = get_config('system', 'theme');
        if (!$default_theme) {
            $default_theme = 'redbasic';
        }

        $themespec = explode(':', App::$channel['channel_theme']);
        $existing_theme = $themespec[0];
        $existing_schema = $themespec[1];

        $theme = (($existing_theme) ? $existing_theme : $default_theme);

        $allowed_themes_str = get_config('system', 'allowed_themes');
        $allowed_themes_raw = explode(',', $allowed_themes_str);
        $allowed_themes = [];
        if (count($allowed_themes_raw)) {
            foreach ($allowed_themes_raw as $x) {
                if (strlen(trim($x)) && is_dir("view/theme/$x")) {
                    $allowed_themes[] = trim($x);
                }
            }
        }


        $themes = [];
        $files = glob('view/theme/*');
        if ($allowed_themes) {
            foreach ($allowed_themes as $th) {
                $f = $th;

                $info = Theme::get_info($th);
                $compatible = Addon::check_versions($info);
                if (!$compatible) {
                    $themes[$f] = sprintf(t('%s - (Incompatible)'), $f);
                    continue;
                }

                $is_experimental = file_exists('view/theme/' . $th . '/experimental');
                $unsupported = file_exists('view/theme/' . $th . '/unsupported');
                $is_library = file_exists('view/theme/' . $th . '/library');

                if (!$is_experimental or ($is_experimental && (get_config('experimentals', 'exp_themes') == 1 or get_config('experimentals', 'exp_themes') === false))) {
                    $theme_name = (($is_experimental) ? sprintf(t('%s - (Experimental)'), $f) : $f);
                    if (!$is_library) {
                        $themes[$f] = $theme_name;
                    }
                }
            }
        }

        $theme_selected = ((array_key_exists('theme', $_SESSION) && $_SESSION['theme']) ? $_SESSION['theme'] : $theme);

        if (strpos($theme_selected, ':')) {
            $theme_selected = explode(':', $theme_selected)[0];
        }


        $preload_images = get_pconfig(local_channel(), 'system', 'preload_images');

        $user_scalable = get_pconfig(local_channel(), 'system', 'user_scalable', '0');

        $browser_update = intval(get_pconfig(local_channel(), 'system', 'update_interval', 30000)); // default if not set: 30 seconds
        $browser_update = (($browser_update < 15000) ? 15 : $browser_update / 1000); // minimum 15 seconds

        $itemspage = intval(get_pconfig(local_channel(), 'system', 'itemspage'));
        $itemspage = (($itemspage > 0 && $itemspage < 101) ? $itemspage : 20); // default if not set: 20 items

        $nosmile = get_pconfig(local_channel(), 'system', 'no_smilies');
        $nosmile = (($nosmile === false) ? '0' : $nosmile); // default if not set: 0

        $theme_config = "";
        if (($themeconfigfile = $this->get_theme_config_file($theme)) != null) {
            require_once($themeconfigfile);
            if (class_exists('\\Code\\Theme\\' . ucfirst($theme) . 'Config')) {
                $clsname = '\\Code\\Theme\\' . ucfirst($theme) . 'Config';
                $thm_config = new $clsname();
                $schemas = $thm_config->get_schemas();
                $theme_config = $thm_config->get();
            }
        }

        // logger('schemas: ' . print_r($schemas,true));

        $tpl = Theme::get_template("settings_display.tpl");
        $o = replace_macros($tpl, [
            '$ptitle' => t('Display Settings'),
            '$d_tset' => t('Theme Settings'),
            '$d_ctset' => t('Custom Theme Settings'),
            '$d_cset' => t('Content Settings'),
            '$form_security_token' => get_form_security_token("settings_display"),
            '$submit' => t('Submit'),
            '$baseurl' => z_root(),
            '$uid' => local_channel(),

            '$theme' => (($themes) ? ['theme', t('Display Theme:'), $theme_selected, '', $themes, 'preview'] : false),
            '$schema' => ['schema', t('Select scheme'), $existing_schema, '', $schemas],
            '$filter_menu_open' => [ 'filter_menu_open', t('Open Activity Filter tool by default'), PConfig::Get(local_channel(), 'system','filter_menu_open'), t('Default is closed'), $yes_no ],
            '$preload_images' => ['preload_images', t("Preload images before rendering the page"), $preload_images, t("The subjective page load time will be longer but the page will be ready when displayed"), $yes_no],
            '$user_scalable' => ['user_scalable', t("Enable user zoom on mobile devices"), $user_scalable, '', $yes_no],
            '$ajaxint' => ['browser_update', t("Update notifications every xx seconds"), $browser_update, t('Minimum of 15 seconds, no maximum')],
            '$itemspage' => ['itemspage', t("Maximum number of conversations to load at any time:"), $itemspage, t('Maximum of 100 items')],
            '$nosmile' => ['nosmile', t("Show emoticons (smilies) as images"), 1 - intval($nosmile), '', $yes_no],
            '$channel_menu' => ['channel_menu', t('Provide channel menu in navigation bar'), get_pconfig(local_channel(), 'system', 'channel_menu', get_config('system', 'channel_menu', 0)), t('Default: channel menu located in app menu'), $yes_no],
            '$layout_editor' => t('System Page Layout Editor - (advanced)'),
            '$theme_config' => $theme_config,
            '$expert' => Features::enabled(local_channel(), 'advanced_theming'),
            '$channel_divmore_height' => ['channel_divmore_height', t('Channel page max height of content (in pixels)'), ((get_pconfig(local_channel(), 'system', 'channel_divmore_height')) ? get_pconfig(local_channel(), 'system', 'channel_divmore_height') : 400), t('click to expand content exceeding this height')],
            '$stream_divmore_height' => ['stream_divmore_height', t('Stream page max height of content (in pixels)'), ((get_pconfig(local_channel(), 'system', 'stream_divmore_height')) ? get_pconfig(local_channel(), 'system', 'stream_divmore_height') : 400), t('click to expand content exceeding this height')],
            '$indentpx' => ['indentpx', t('Indent threaded comments this many pixels from the parent'), intval(get_pconfig(local_channel(), 'system', 'thread_indent_px', get_config('system', 'thread_indent_px', 0))), t('0-20')],

        ]);

        Hook::call('display_settings', $o);
        return $o;
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
