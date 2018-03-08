<?php

namespace Zotlabs\Module;


class Hashtags extends \Zotlabs\Web\Controller {

	function init() {
		$result = [];

		logger(print_r($_REQUEST,true));

		$t = escape_tags($_REQUEST['t']);
		if(! $t)
			json_return_and_die($result);

		$r = q("select distinct(term) from term where term like '%s' and ttype = %d order by term",
			dbesc($t . '%'),
			intval(TERM_HASHTAG)
		);
		if($r) {
			foreach($r as $rv) {
				$result[] = [ 'text' => strtolower($rv['term']) ];
			}
		}
		logger(print_r($result,true));
		json_return_and_die($result); 
	}
}