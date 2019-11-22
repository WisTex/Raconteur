<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\Libzotdir;

class Pubsites extends \Zotlabs\Web\Controller {

	function get() {

		$dirmode = intval(get_config('system','directory_mode'));
	
		if (($dirmode == DIRECTORY_MODE_PRIMARY) || ($dirmode == DIRECTORY_MODE_STANDALONE)) {
			$url = z_root() . '/dirsearch';
		}
		
		if (! $url) {
			$directory = Libzotdir::find_upstream_directory($dirmode);
			$url = $directory['url'] . '/dirsearch';
		}
		
		$url .= '/sites';

		$o .= '<div class="generic-content-wrapper">';
	
		$o .= '<div class="section-title-wrapper"><h2>' . t('Public Hubs') . '</h2></div>';
	
		$o .= '<div class="section-content-tools-wrapper"><div class="descriptive-text">' . 
			t('The listed hubs allow public registration for the $Projectname network. All hubs in the network are interlinked so membership on any of them conveys membership in the network as a whole. Some hubs may require subscription or provide tiered service plans. The hub itself <strong>may</strong> provide additional details.') . '</div>' . EOL;
	
		$ret = z_fetch_url($url);
		if($ret['success']) {
			$j = json_decode($ret['body'],true);
			if($j) {
				if($j['sites']) {
					$projects = $this->sort_sites($j['sites']);
					foreach($projects as $p => $v) {
						if (ucfirst($p) === 'Osada') {
							// deprecated
							continue;
						}
						$o .= '<strong>' . ucfirst($p) . '</strong>' . EOL;
						$o .= '<table class="table table-striped table-hover"><tr><td>' . t('Hub URL') . '</td><td>' . t('Access Type') . '</td><td>' . t('Registration Policy') . '</td><td>' . t('Software') . '</td>';
						$o .= '</tr>';

						usort($v, [ $this, 'sort_versions' ]);
						foreach ($v as $jj) {
							if(strpos($jj['version'],' ')) {
								$x = explode(' ', $jj['version']);
								if($x[1])
									$jj['version'] = $x[1];
							}
							$m = parse_url($jj['url']);
							$host = strtolower(substr($jj['url'],strpos($jj['url'],'://')+3));
							$location = '';
							if(!empty($jj['location'])) { 
								$location = '<p title="' . t('Location') . '" style="margin: 5px 5px 0 0; text-align: right"><i class="fa fa-globe"></i> ' . $jj['location'] . '</p>'; 
							}
							else {
								$location = '<br>&nbsp;';
							}
							$urltext = str_replace(array('https://'), '', $jj['url']);
							$o .= '<tr><td><a href="'. (($jj['sellpage']) ? $jj['sellpage'] : $jj['url'] . '/register' ) . '" ><i class="fa fa-link"></i> ' . $urltext . '</a>' . $location . '</td><td>' . $jj['access'] . '</td><td>' . $jj['register'] . '</td><td>' . ucwords($jj['project']) . (($jj['version']) ? ' ' . $jj['version'] : '') . '</td>';
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
