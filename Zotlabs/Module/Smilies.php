<?php
namespace Zotlabs\Module;


use App;
use Zotlabs\Web\Controller;

class Smilies extends Controller {

	function get() { 
		if (App::$argv[1]==="json"){
			$tmp = list_smilies();
			$results = [];
			for($i = 0; $i < count($tmp['texts']); $i++) {
				$results[] = array('text' => $tmp['texts'][$i], 'icon' => $tmp['icons'][$i]);
			}
			json_return_and_die($results);
		}
		else {
			return smilies('',true);
		}
	}
	
}
