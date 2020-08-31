<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\Libzotdir;

class Sites extends \Zotlabs\Web\Controller {

	function get() {

		$url = z_root() . '/dirsearch/sites';
		
		$o .= '<div class="generic-content-wrapper">';
	
		$o .= '<div class="section-title-wrapper"><h2>' . t('Affiliated Sites') . '</h2></div>';
	
		$o .= '<div class="section-content-tools-wrapper"><div class="descriptive-text">' .
			
		t('This page provides information about related projects and websites that are currently known to this system. These are a small fraction of the thousands of affiliated fediverse websites.') . '</div>' . EOL;
	
		$ret = z_fetch_url($url);
		if ($ret['success']) {
			$j = json_decode($ret['body'],true);
			if ($j) {
				if ($j['sites']) {
					$projects = $this->sort_sites($j['sites']);
					foreach ($projects as $p => $v) {
						if (! $p) {
							continue;
						}
						$o .= '<strong>' . ucfirst($p) . '</strong>' . EOL;
						$o .= '<table class="table table-striped table-hover"><tr><td style="width: 50%;">' . t('URL') . '</td><td style="width: 15%;">' . t('Access Type') . '</td><td style="width: 15%;">' . t('Registration Policy') . '</td><td style="width: 20%">' . t('Software') . '</td>';
						$o .= '</tr>';

						usort($v, [ $this, 'sort_versions' ]);
						foreach ($v as $jj) {
							if (strpos($jj['version'],' ')) {
								$x = explode(' ', $jj['version']);
								if ($x[1]) {
									$jj['version'] = $x[1];
								}
							}
							$m = parse_url($jj['url']);
							$host = strtolower($m['host']);
							$location = '<br>&nbsp;';
							if (!empty($jj['location'])) { 
								$location = '<br><span title="' . t('Location') . '" style="margin: 5px 5px 0 0; text-align: right"><i class="fa fa-globe"></i> ' . $jj['location'] . '</span>'; 
							}
							
							$disabled = (($jj['access'] === 'private') ? true : false);
							$o .= '<tr><td>' . (($disabled) ? '' : '<a href="'. (($jj['sellpage']) ? $jj['sellpage'] : $jj['url'] . '/register' ) . '" ><i class="fa fa-link"></i> ') . $host . (($disabled) ? '' : '</a>') . $location . '</td><td>' . $jj['access'] . '</td><td>' . (($disabled) ? '&nbsp;' : $jj['register']) . '</td><td>' . ucwords($jj['project']) . (($jj['version']) ? ' ' . $jj['version'] : '') . '</td>';
							$o .=  '</tr>';
						}
						$o .= '</table>';
						$o .= '<br><br>';
					}
				}				
				$o .= '</div></div>';
			}
		}
		return $o;

	}

	function sort_sites($a) {
		$ret = [];
		if($a) {
			foreach($a as $e) {
				$projectname = explode(' ',$e['project']);
				$ret[$projectname[0]][] = $e;
			}
		}
		$projects = array_keys($ret);
		rsort($projects);
		
		$newret = [];
		foreach($projects as $p) {
			$newret[$p] = $ret[$p];
		}

		return $newret;
	}

	function sort_versions($a,$b) {
		return version_compare($b['version'],$a['version']);
	}
}
