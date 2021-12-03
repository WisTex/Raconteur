<?php

namespace Zotlabs\Lib;


class AbConfig {

	public static function Load($chan, $xhash, $family = '') {
		if($family)
			$where = sprintf(" and cat = '%s' ",dbesc($family));
		$r = q("select * from abconfig where chan = %d and xchan = '%s' $where",
			intval($chan),
			dbesc($xhash)
		);
		return $r;
	}


	public static function Get($chan, $xhash, $family, $key, $default = false) {
		$r = q("select * from abconfig where chan = %d and xchan = '%s' and cat = '%s' and k = '%s' limit 1",
			intval($chan),
			dbesc($xhash),
			dbesc($family),
			dbesc($key)		
		);
		if($r) {
			return unserialise($r[0]['v']);
		}
		return $default;
	}


	public static function Set($chan, $xhash, $family, $key, $value) {

		$dbvalue = ((is_array($value))  ? serialise($value) : $value);
		$dbvalue = ((is_bool($dbvalue)) ? intval($dbvalue)  : $dbvalue);

		if(self::Get($chan,$xhash,$family,$key) === false) {
			$r = q("insert into abconfig ( chan, xchan, cat, k, v ) values ( %d, '%s', '%s', '%s', '%s' ) ",
				intval($chan),
				dbesc($xhash),
				dbesc($family),
				dbesc($key),
				dbesc($dbvalue)		
			);
		}
		else {
			$r = q("update abconfig set v = '%s' where chan = %d and xchan = '%s' and cat = '%s' and k = '%s' ",
				dbesc($dbvalue),		
				dbesc($chan),
				dbesc($xhash),
				dbesc($family),
				dbesc($key)
			);
		}
	
		if($r)
			return $value;
		return false;
	}


	public static function Delete($chan, $xhash, $family, $key) {

		$r = q("delete from abconfig where chan = %d and xchan = '%s' and cat = '%s' and k = '%s' ",
			intval($chan),
			dbesc($xhash),
			dbesc($family),
			dbesc($key)
		);

		return $r;
	}

}
