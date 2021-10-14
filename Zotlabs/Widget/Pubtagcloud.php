<?php

namespace Zotlabs\Widget;

class Pubtagcloud {

	function widget($arr) {

		$trending = ((array_key_exists('trending',$arr)) ? intval($arr['trending']) : 0);
	    if ((observer_prohibited(true))) {
            return EMPTY_STR;
        }

        if (! intval(get_config('system','open_pubstream',1))) {
            if (! local_channel()) {
                return EMPTY_STR;
            }
        }

		$public_stream_mode = intval(get_config('system','public_stream_mode', PUBLIC_STREAM_NONE));

        if (! $public_stream_mode) {
            return EMPTY_STR;
        }

		$safemode = get_xconfig(get_observer_hash(),'directory','safemode',1);



		$limit = ((array_key_exists('limit', $arr)) ? intval($arr['limit']) : 75);

		return pubtagblock($public_stream_mode, $limit, $trending, $safemode);

		return '';
	}
}
