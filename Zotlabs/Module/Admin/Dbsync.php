<?php

namespace Zotlabs\Module\Admin;



class Dbsync {
	

	function get() {
		$o = '';
	
		if(argc() > 3 && intval(argv(3)) && argv(2) === 'mark') {
			// remove the old style config if it exists
			del_config('database', 'update_r' . intval(argv(3)));
			set_config('database', '_' . intval(argv(3)), 'success');
			if(intval(get_config('system','db_version')) < intval(argv(3)))
				set_config('system','db_version',intval(argv(3)));
			info( t('Update has been marked successful') . EOL);
			goaway(z_root() . '/admin/dbsync');
		}

		if(argc() > 3 && intval(argv(3)) && argv(2) === 'verify') {

			$s = '_' . intval(argv(3));
			$cls = '\\Zotlabs\Update\\' . $s ;
			if(class_exists($cls)) {
				$c =  new $cls();
				if(method_exists($c,'verify')) {
					$retval = $c->verify();
					if($retval === UPDATE_FAILED) {
						$o .= sprintf( t('Verification of update %s failed. Check system logs.'), $s); 
					}
					elseif($retval === UPDATE_SUCCESS) {
						$o .= sprintf( t('Update %s was successfully applied.'), $s);
						set_config('database',$s, 'success');
					}
					else
						$o .= sprintf( t('Verifying update %s did not return a status. Unknown if it succeeded.'), $s);
				}
				else {
						$o .= sprintf( t('Update %s does not contain a verification function.'), $s );
				}
			}
			else
				$o .= sprintf( t('Update function %s could not be found.'), $s);
	
			return $o;





			// remove the old style config if it exists
			del_config('database', 'update_r' . intval(argv(3)));
			set_config('database', '_' . intval(argv(3)), 'success');
			if(intval(get_config('system','db_version')) < intval(argv(3)))
				set_config('system','db_version',intval(argv(3)));
			info( t('Update has been marked successful') . EOL);
			goaway(z_root() . '/admin/dbsync');
		}

		if(argc() > 2 && intval(argv(2))) {
			$x = intval(argv(2));
			$s = '_' . $x;
			$cls = '\\Zotlabs\Update\\' . $s ;
			if(class_exists($cls)) {
				$c =  new $cls();
				$retval = $c->run();
				if($retval === UPDATE_FAILED) {
					$o .= sprintf( t('Executing update procedure %s failed. Check system logs.'), $s); 
				}
				elseif($retval === UPDATE_SUCCESS) {
					$o .= sprintf( t('Update %s was successfully applied.'), $s);
					set_config('database',$s, 'success');
				}
				else
					$o .= sprintf( t('Update %s did not return a status. It cannot be determined if it was successful.'), $s);
			}
			else
				$o .= sprintf( t('Update function %s could not be found.'), $s);
	
			return $o;
		}
	
		$failed = array();
		$r = q("select * from config where cat = 'database' ");
		if(count($r)) {
			foreach($r as $rr) {
				$upd = intval(substr($rr['k'],-4));
				if($rr['v'] === 'success')
					continue;
				$failed[] = $upd;
			}
		}
		if(count($failed)) {
			$o = replace_macros(get_markup_template('failed_updates.tpl'),array(
				'$base'   => z_root(),
				'$banner' => t('Failed Updates'),
				'$desc'   => '',
				'$mark'   => t('Mark success (if update was manually applied)'),
				'$verify' => t('Attempt to verify this update if a verification procedure exists'),
				'$apply'  => t('Attempt to execute this update step automatically'),
				'$failed' => $failed
		));
		}
		else {
			return '<div class="generic-content-wrapper-styled"><h3>' . t('No failed updates.') . '</h3></div>';
		}
	
		return $o;
	}
}