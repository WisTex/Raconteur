<?php

namespace Zotlabs\Module\Settings;


class Features {

	function post() {
		check_form_security_token_redirectOnErr('/settings/features', 'settings_features');
	
		$features = get_features(false);

		foreach($features as $fname => $fdata) {
			foreach(array_slice($fdata,1) as $f) {
				$k = $f[0];
				if(array_key_exists("feature_$k",$_POST))
					set_pconfig(local_channel(),'feature',$k, (string) $_POST["feature_$k"]);
				else
					set_pconfig(local_channel(),'feature', $k, '');
			}
		}
		build_sync_packet();
		return;
	}

	function get() {
		
		$arr = [];
		$harr = [];

		if(intval($_REQUEST['techlevel']))
			$level = intval($_REQUEST['techlevel']);
		else {
 			$level = get_account_techlevel();
		}

		if(! intval($level)) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		$techlevels = \Zotlabs\Lib\Techlevels::levels();

		// This page isn't accessible at techlevel 0

		unset($techlevels[0]);

		$def_techlevel = (($level > 0) ? $level : 1);
		$techlock = get_config('system','techlevel_lock');

		$all_features_raw = get_features(false);

		foreach($all_features_raw as $fname => $fdata) {
			foreach(array_slice($fdata,1) as $f) {
				$harr[$f[0]] = ((intval(feature_enabled(local_channel(),$f[0]))) ? "1" : '');
			}
		}

		$features = get_features(true,$level);

		foreach($features as $fname => $fdata) {
			$arr[$fname] = array();
			$arr[$fname][0] = $fdata[0];
			foreach(array_slice($fdata,1) as $f) {
				$arr[$fname][1][] = array('feature_' . $f[0],$f[1],((intval(feature_enabled(local_channel(),$f[0]))) ? "1" : ''),$f[2],array(t('Off'),t('On')));
				unset($harr[$f[0]]);
			}
		}
			
		$tpl = get_markup_template("settings_features.tpl");
		$o .= replace_macros($tpl, array(
			'$form_security_token' => get_form_security_token("settings_features"),
			'$title'	 => t('Additional Features'),
			'$techlevel' => [ 'techlevel', t('Your technical skill level'), $def_techlevel, t('Used to provide a member experience and additional features consistent with your comfort level'), $techlevels ],
			'$techlock'  => $techlock,
			'$features'  => $arr,
			'$hiddens'   => $harr,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));
	
		return $o;
	}

}
