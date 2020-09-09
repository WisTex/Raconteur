<?php

namespace Zotlabs\Widget;


class Site_projects {

	function widget($args) {



		$r = q("select site_project, count(site_project) as total from site where site_project != '' and site_flags != 256 and site_dead = 0 group by site_project order by site_project desc");

		$results = [];
		
		if ($r) {
		
			foreach ($r as $rv) {
				$result = [];
				$result['name'] = $rv['site_project'];
				$result['cname'] = ucfirst($result['name']);
				if ($rv['site_project'] === $_REQUEST['project']) {
					$result['selected'] = true;
				}
				$result['total'] = $rv['total'];
				$results[] = $result;
			}	

			$o = replace_macros(get_markup_template('site_projects.tpl'), [
				'$title' => t('Projects'),
				'$desc' => '',
				'$all' => t('All projects'),
				'base' => z_root() . '/sites',
				'$sel_all' => (($_REQUEST['project']) ? false : true),
				'$terms' => $results
			]);
			
			return $o;
		}		
	}
}
