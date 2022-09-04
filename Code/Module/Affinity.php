<?php

namespace Code\Module;

use Code\Lib\Apps;
use Code\Lib\Libsync;
use Code\Web\Controller;
use Code\Extend\Hook;
use Code\Render\Theme;


class Affinity extends Controller
{

    public function post()
    {

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Friend Zoom'))) {
            return;
        }

        if ($_POST['affinity-submit']) {
            $cmax = intval($_POST['affinity_cmax']);
            if ($cmax < 0 || $cmax > 99) {
                $cmax = 99;
            }
            $cmin = intval($_POST['affinity_cmin']);
            if ($cmin < 0 || $cmin > 99) {
                $cmin = 0;
            }
            set_pconfig(local_channel(), 'affinity', 'cmin', 0);
            set_pconfig(local_channel(), 'affinity', 'cmax', $cmax);

            info(t('Friend Zoom settings updated.') . EOL);
        }

        Libsync::build_sync_packet();
    }


    public function get()
    {

        $desc = t('This app (when installed) presents a slider control in your connection editor and also on your stream page. The slider represents your degree of friendship with each connection. It allows you to zoom in or out and display conversations from only your closest friends or everybody in your stream.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Friend Zoom'))) {
            return $text;
        }

        $text .= EOL . t('The number below represents the default maximum slider position for your stream page as a percentage.') . EOL . EOL;

        $setting_fields = $text;

        $cmax = intval(get_pconfig(local_channel(), 'affinity', 'cmax'));
        $cmax = (($cmax) ? $cmax : 99);
//      $setting_fields .= replace_macros(Theme::get_template('field_input.tpl'), array(
//          '$field'    => array('affinity_cmax', t('Default maximum affinity level'), $cmax, t('0-99 default 99'))
//      ));

        if (Apps::system_app_installed(local_channel(), 'Friend Zoom')) {
            $labels = [
                0 => t('Me'),
                20 => t('Family'),
                40 => t('Friends'),
                60 => t('Peers'),
                80 => t('Connections'),
                99 => t('All')
            ];
            Hook::call('affinity_labels', $labels);

            $tpl = Theme::get_template('affinity.tpl');
            $x = replace_macros($tpl, [
                '$cmin' => 0,
                '$cmax' => $cmax,
                '$lbl' => t('Default friend zoom in/out'),
                '$refresh' => t('Refresh'),
                '$labels' => $labels,
            ]);


            $arr = ['html' => $x];
            Hook::call('affinity_slider', $arr);
            $setting_fields .= $arr['html'];
        }

        $s = replace_macros(Theme::get_template('generic_app_settings.tpl'), [
            '$addon' => ['affinity', '' . t('Friend Zoom Settings'), '', t('Submit')],
            '$content' => $setting_fields
        ]);

        return $s;
    }
}
