<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;



class Sitelist extends Controller {

	function init() {
	
		$start = (($_REQUEST['start']) ? intval($_REQUEST['start']) : 0);
		$limit = ((intval($_REQUEST['limit'])) ? intval($_REQUEST['limit']) : 30);
		$order = (($_REQUEST['order']) ? $_REQUEST['order'] : 'random');
		$open = (($_REQUEST['open']) ? intval($_REQUEST['open']) : false);
	
	
		$sql_order = " order by site_url ";
		$rand = db_getfunc('rand');

		if ($order == 'random') {
			$sql_order = " order by $rand ";
		}
		
		$sql_limit = " LIMIT $limit OFFSET $start ";
	
		$sql_extra = "";

		if ($open) {
			// only return sites with open registration
			$sql_extra = " and site_register = " . intval(REGISTER_OPEN) . " ";
		}
		
		$result = [ 'success' => false ];
	
		$r = q("select count(site_url) as total from site where site_type = %d and site_dead = 0 $sql_extra ",
			intval(SITE_TYPE_ZOT)
		);
		
		if ($r) {
			$result['total'] = intval($r[0]['total']);
		}
		
		$result['start'] = $start;
		$result['limit'] = $limit;	
	
		$r = q("select * from site where site_type = %d and site_dead = 0 $sql_extra $sql_order $sql_limit",
			intval(SITE_TYPE_ZOT)
		);
	
		$result['results'] = 0;
		$result['entries'] = [];
	
		if($r) {
			$result['success'] = true;		
			$result['results'] = count($r);
			
			foreach ($r as $rv) {
				$result['entries'][] = [ 'url' => $rv['site_url'] ];
			}
	
		}
	
		json_return_and_die($result);
	}
}
