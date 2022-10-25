<?php

namespace Code\Widget;

use App;

require_once('include/photos.php');

class Photo_rand implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        $style = false;

        if (array_key_exists('album', $arguments) && isset($arguments['album'])) {
            $album = $arguments['album'];
        } else {
            $album = '';
        }

        $channel_id = 0;
        if (array_key_exists('channel_id', $arguments) && intval($arguments['channel_id'])) {
            $channel_id = intval($arguments['channel_id']);
        }
        if (!$channel_id) {
            $channel_id = App::$profile_uid;
        }
        if (!$channel_id) {
            return '';
        }

        $scale = ((array_key_exists('scale', $arguments)) ? intval($arguments['scale']) : 0);

        $ret = photos_list_photos(['channel_id' => $channel_id], App::get_observer(), $album);

        $filtered = [];
        if ($ret['success'] && $ret['photos']) {
            foreach ($ret['photos'] as $p) {
                if ($p['imgscale'] == $scale) {
                    $filtered[] = $p['src'];
                }
            }
        }

        if ($filtered) {
            $e = mt_rand(0, count($filtered) - 1);
            $url = $filtered[$e];
        }

        if (!str_starts_with($url, 'http')) {
            return '';
        }

        if (array_key_exists('style', $arguments) && isset($arguments['style'])) {
            $style = $arguments['style'];
        }

        // ensure they can't sneak in an eval(js) function

        if (str_contains($style, '(')) {
            return '';
        }

        $url = zid($url);

        $o = '<div class="widget">';

        $o .= '<img class="zrl" '
            . (($style) ? ' style="' . $style . '"' : '')
            . ' src="' . $url . '" alt="' . t('photo/image') . '">';

        $o .= '</div>';

        return $o;
    }
}
