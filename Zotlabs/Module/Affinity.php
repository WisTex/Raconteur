<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;

class Affinity extends \Zotlabs\Web\Controller {

	function post() {

		if(! ( local_channel() && Apps::system_app_installed(local_channel(),'Affinity Tool'))) {
            return;
        }

		if($_POST['affinity-submit']) {
			$cmax = intval($_POST['affinity_cmax']);
			if($cmax < 0 || $cmax > 99)
				$cmax = 99;
			$cmin = intval($_POST['affinity_cmin']);
			if($cmin < 0 || $cmin > 99)
				$cmin = 0;
			set_pconfig(local_channel(),'affinity','cmin',$cmin);
			set_pconfig(local_channel(),'affinity','cmax',$cmax);

			info( t('Affinity Tool settings updated.') . EOL);

		}
		
		Libsync::build_sync_packet();

	}


	function get() {

        $desc = t('This app (when installed) presents a slider control in your connection editor and also on your network page. The slider represents your degree of friendship or <em>affinity</em> with each connection. It allows you to zoom in or out and display conversations from only your closest friends or everybody in your stream.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if(! ( local_channel() && Apps::system_app_installed(local_channel(),'Affinity Tool'))) {
            return $text;
        }

		$text .= EOL . t('The numbers below represent the minimum and maximum slider default positions for your network/stream page as a percentage.') . EOL . EOL; 			

		$setting_fields = $text;

		$cmax = intval(get_pconfig(local_channel(),'affinity','cmax'));
		$cmax = (($cmax) ? $cmax : 99);
		$setting_fields .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'    => array('affinity_cmax', t('Default maximum affinity level'), $cmax, t('0-99 default 99'))
		));
		$cmin = intval(get_pconfig(local_channel(),'affinity','cmin'));
		$cmin = (($cmin) ? $cmin : 0);
		$setting_fields .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'    => array('affinity_cmin', t('Default minimum affinity level'), $cmin, t('0-99 - default 0'))
		));

		$s .= replace_macros(get_markup_template('generic_app_settings.tpl'), array(
			'$addon'    => array('affinity', '' . t('Affinity Tool Settings'), '', t('Submit')),
			'$content'  => $setting_fields
		));

		return $s;
	}


}