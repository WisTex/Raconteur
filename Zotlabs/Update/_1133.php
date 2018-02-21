<?php

namespace Zotlabs\Update;

class _1133 {
function run() {
	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("CREATE TABLE xperm (
			xp_id serial NOT NULL,
			xp_client varchar( 20 ) NOT NULL DEFAULT '',
			xp_channel bigint NOT NULL DEFAULT '0',
			xp_perm varchar( 64 ) NOT NULL DEFAULT '',
			PRIMARY KEY (xp_id) )");
		$r2 = 0;
		foreach(array('xp_client', 'xp_channel', 'xp_perm') as $fld)
			$r2 += ((q("create index $fld on xperm ($fld)") == false) ? 0 : 1);
			
		$r = (($r1 && $r2) ? true : false);
	}
	else {
		$r = q("CREATE TABLE IF NOT EXISTS `xperm` (
			`xp_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`xp_client` VARCHAR( 20 ) NOT NULL DEFAULT '',
			`xp_channel` INT UNSIGNED NOT NULL DEFAULT '0',
			`xp_perm` VARCHAR( 64 ) NOT NULL DEFAULT '',
			KEY `xp_client` (`xp_client`),
			KEY `xp_channel` (`xp_channel`),
			KEY `xp_perm` (`xp_perm`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");
	}
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}