<?php

namespace Zotlabs\Widget;

class Affinity {

	function widget($arr) {

		if(! local_channel())
			return '';
	
		$default_cmin = ((feature_enabled(local_channel(),'affinity')) ? get_pconfig(local_channel(),'affinity','cmin',0) : 0);
		$default_cmax = ((feature_enabled(local_channel(),'affinity')) ? get_pconfig(local_channel(),'affinity','cmax',99) : 99);

		$cmin = ((x($_REQUEST,'cmin')) ? intval($_REQUEST['cmin']) : $default_cmin);
		$cmax = ((x($_REQUEST,'cmax')) ? intval($_REQUEST['cmax']) : $default_cmax);


		if(feature_enabled(local_channel(),'affinity')) {

			$labels = array(
				t('Me'),
				t('Family'),
				t('Friends'),
				t('Acquaintances'),
				t('All')
			);
			call_hooks('affinity_labels',$labels);
			$label_str = '';

			if($labels) {
				foreach($labels as $l) {
					if($label_str) {
						$label_str .= ", '|'";
						$label_str .= ", '" . $l . "'";
					}
					else
						$label_str .= "'" . $l . "'";
				}
			}

			$tpl = get_markup_template('main_slider.tpl');
			$x = replace_macros($tpl,array(
				'$val' => $cmin . ',' . $cmax,
				'$refresh' => t('Refresh'),
				'$labels' => $label_str,
			));
		
			$arr = array('html' => $x);
			call_hooks('main_slider',$arr);
			return $arr['html'];
		}
 		return '';
	}
}
 