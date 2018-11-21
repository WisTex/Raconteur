<?php

namespace Zotlabs\Update;

class _1223 {
	
	function run() {
		foreach( [ 'abconfig','config','pconfig','xconfig','iconfig' ] as $tbl) {
			$r = q("select id, v from %s where v like 'a:%' ",
				dbesc($tbl)
			);
			if($r) {
				foreach($r as $rv) {
					$s = unserialize($rv['v']);
					if(is_array($s))
						$s = serialise($s);
					else
						$s = $rv['v'];
					q("update %s set v = '%s' where id = %d",
						dbesc($tbl),
						dbesc($s),
						dbesc($rv['id'])
					);
				}
			}
		}
		return UPDATE_SUCCESS;
	}
}
