<?php

namespace Code\Widget;

use Code\Lib\Apps;
use Code\Extend\Hook;
use Code\Render\Theme;


class Affinity
{

    public function widget($arr)
    {

        if (!local_channel()) {
            return '';
        }

        $default_cmin = ((Apps::system_app_installed(local_channel(), 'Friend Zoom')) ? get_pconfig(local_channel(), 'affinity', 'cmin', 0) : 0);
        $default_cmax = ((Apps::system_app_installed(local_channel(), 'Friend Zoom')) ? get_pconfig(local_channel(), 'affinity', 'cmax', 99) : 99);

        $cmin = ((x($_REQUEST, 'cmin')) ? intval($_REQUEST['cmin']) : $default_cmin);
        $cmax = ((x($_REQUEST, 'cmax')) ? intval($_REQUEST['cmax']) : $default_cmax);


        if (Apps::system_app_installed(local_channel(), 'Friend Zoom')) {
            $labels = array(
                0 => t('Me'),
                20 => t('Family'),
                40 => t('Friends'),
                60 => t('Peers'),
                80 => t('Connections'),
                99 => t('All')
            );
            Hook::call('affinity_labels', $labels);

            $tpl = Theme::get_template('main_slider.tpl');
            $x = replace_macros($tpl, [
                '$cmin' => $cmin,
                '$cmax' => $cmax,
                '$lbl' => t('Friend zoom in/out'),
                '$refresh' => t('Refresh'),
                '$labels' => $labels,
            ]);

            $arr = array('html' => $x);
            Hook::call('main_slider', $arr);
            return $arr['html'];
        }
        return '';
    }
}
