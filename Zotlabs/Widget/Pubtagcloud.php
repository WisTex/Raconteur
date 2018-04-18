<?php

namespace Zotlabs\Widget;

class Pubtagcloud {

	function widget($arr) {

		$trending = ((array_key_exists('trending',$arr)) ? intval($arr['trending']) : 0);
	    if((observer_prohibited(true))) {
            return EMPTY_STR;
        }

        if(! intval(get_config('system','open_pubstream',1))) {
            if(! get_observer_hash()) {
                return EMPTY_STR;
            }
        }

        $site_firehose = ((intval(get_config('system','site_firehose',0))) ? true : false);
        $net_firehose  = ((get_config('system','disable_discover_tab',1)) ? false : true);

        if(! ($site_firehose || $net_firehose)) {
            return EMPTY_STR;
        }

        if($net_firehose) {
            $site_firehose = false;
        }

		$safemode = get_xconfig(get_observer_hash(),'directory','safemode',1);



		$limit = ((array_key_exists('limit', $arr)) ? intval($arr['limit']) : 75);

		return pubtagblock($net_firehose,$site_firehose, $limit, $trending, $safemode);

		return '';
	}
}
