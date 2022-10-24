<?php

namespace Code\Widget;

use App;
use Code\Lib\System;
use Code\Lib\Channel;
use Code\Render\Theme;


class Cover_photo implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        $o = '';

        if (App::$module === 'channel' && $_REQUEST['mid']) {
            return '';
        }

        $channel_id = 0;

        $site_banner = false;

        if (App::$module === 'home') {
            $channel = Channel::get_system();
            $channel_id = $channel['channel_id'];
            $site_banner = System::get_site_name();
        }

        if (array_key_exists('channel_id', $arguments) && intval($arguments['channel_id'])) {
            $channel_id = intval($arguments['channel_id']);
        }
        if (!$channel_id) {
            $channel_id = App::$profile_uid;
        }
        if (!$channel_id) {
            return '';
        }

        // only show cover photos once per login session
        $hide_cover = false;
        if (array_key_exists('channels_visited', $_SESSION) && is_array($_SESSION['channels_visited']) && in_array($channel_id, $_SESSION['channels_visited'])) {
            $hide_cover = true;
        }
        if (!array_key_exists('channels_visited', $_SESSION)) {
            $_SESSION['channels_visited'] = [];
        }
        $_SESSION['channels_visited'][] = $channel_id;

        $channel = Channel::from_id($channel_id);

        if (array_key_exists('style', $arguments) && isset($arguments['style'])) {
            $style = $arguments['style'];
        } else {
            $style = 'width:100%; height: auto;';
        }

        // ensure they can't sneak in an eval(js) function

        if (strpbrk($style, '(\'"<>') !== false) {
            $style = '';
        }

        if (array_key_exists('title', $arguments) && isset($arguments['title'])) {
            $title = $arguments['title'];
        } else {
            $title = $channel['channel_name'];
        }


        if (array_key_exists('subtitle', $arguments) && isset($arguments['subtitle'])) {
            $subtitle = $arguments['subtitle'];
        } else {
            $subtitle = str_replace('@', '&#x40;', $channel['xchan_addr']);
        }


        if ($site_banner) {
            $title = $site_banner;
            $subtitle = '';
        }

        $c = Channel::get_cover_photo($channel_id, 'array');

        if ($c) {
            $o = replace_macros(Theme::get_template('cover_photo_widget.tpl'), [
                '$photo' => $c,
                '$style' => $style,
                '$alt' => t('cover photo'),
                '$title' => $title,
                '$subtitle' => $subtitle,
                '$hovertitle' => t('Click to show more'),
                '$hide_cover' => $hide_cover
            ]);
        }
        return $o;
    }
}
