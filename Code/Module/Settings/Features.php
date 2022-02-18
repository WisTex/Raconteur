<?php

namespace Code\Module\Settings;

use Code\Lib\Libsync;
use Code\Lib as Zlib;
use Code\Render\Theme;


class Features
{

    public function post()
    {
        check_form_security_token_redirectOnErr('/settings/features', 'settings_features');

        $features = Zlib\Features::get(false);

        foreach ($features as $fname => $fdata) {
            foreach (array_slice($fdata, 1) as $f) {
                $k = $f[0];
                if (array_key_exists("feature_$k", $_POST)) {
                    set_pconfig(local_channel(), 'feature', $k, (string)$_POST["feature_$k"]);
                } else {
                    set_pconfig(local_channel(), 'feature', $k, '');
                }
            }
        }
        Libsync::build_sync_packet();
        return;
    }

    public function get()
    {

        $arr = [];
        $harr = [];


        $all_features_raw = Zlib\Features::get(false);

        foreach ($all_features_raw as $fname => $fdata) {
            foreach (array_slice($fdata, 1) as $f) {
                $harr[$f[0]] = ((intval(Zlib\Features::enabled(local_channel(), $f[0]))) ? "1" : '');
            }
        }

        $features = Zlib\Features::get(true);

        foreach ($features as $fname => $fdata) {
            $arr[$fname] = [];
            $arr[$fname][0] = $fdata[0];
            foreach (array_slice($fdata, 1) as $f) {
                $arr[$fname][1][] = array('feature_' . $f[0], $f[1], ((intval(Zlib\Features::enabled(local_channel(), $f[0]))) ? "1" : ''), $f[2], array(t('Off'), t('On')));
                unset($harr[$f[0]]);
            }
        }

        $tpl = Theme::get_template("settings_features.tpl");
        $o .= replace_macros($tpl, array(
            '$form_security_token' => get_form_security_token("settings_features"),
            '$title' => t('Additional Features'),
            '$features' => $arr,
            '$hiddens' => $harr,
            '$baseurl' => z_root(),
            '$submit' => t('Submit'),
        ));

        return $o;
    }
}
