<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Features;

class Features
{

    public function post()
    {
        check_form_security_token_redirectOnErr('/settings/features', 'settings_features');

        $features = get_features(false);

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


        $all_features_raw = get_features(false);

        foreach ($all_features_raw as $fname => $fdata) {
            foreach (array_slice($fdata, 1) as $f) {
                $harr[$f[0]] = ((intval(feature_enabled(local_channel(), $f[0]))) ? "1" : '');
            }
        }

        $features = get_features(true);

        foreach ($features as $fname => $fdata) {
            $arr[$fname] = [];
            $arr[$fname][0] = $fdata[0];
            foreach (array_slice($fdata, 1) as $f) {
                $arr[$fname][1][] = array('feature_' . $f[0], $f[1], ((intval(feature_enabled(local_channel(), $f[0]))) ? "1" : ''), $f[2], array(t('Off'), t('On')));
                unset($harr[$f[0]]);
            }
        }

        $tpl = get_markup_template("settings_features.tpl");
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
