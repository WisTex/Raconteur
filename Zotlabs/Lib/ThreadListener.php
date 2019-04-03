<?php

namespace Zotlabs\Lib;

class ThreadListener {

	static public function store($target_id,$portable_id,$ltype = 0) {
		$x = self::fetch($target_id,$portable_id,$ltype = 0);
		if(! $x) {  
			$r = q("insert into listeners ( target_id, portable_id, ltype ) values ( '%s', '%s' , %d ) ",
				dbesc($target_id),
				dbesc($portable_id),
				intval($ltype)
			);
		}  	
	}

	static public function fetch($target_id,$portable_id,$ltype = 0) {
		$x = q("select * from listeners where target_id = '%s' and portable_id = '%s' and ltype = %d limit 1",
			dbesc($target_id),
			dbesc($portable_id),
			intval($ltype)
		);
		if($x) {
			return $x[0];
		}
		return false;
	}

	static public function fetch_by_target($target_id,$ltype = 0) {
		$x = q("select * from listeners where target_id = '%s' and ltype = %d",
			dbesc($target_id),
			intval($ltype)
		);

		return $x;
	}

	static public function delete_by_target($target_id, $ltype = 0) {
		return q("delete from listeners where target_id = '%s' and ltype = %d",
			dbesc($target_id),
			intval($ltype)
		);
	}

	static public function delete_by_pid($portable_id, $ltype = 0) {
		return q("delete from listeners where portable_id = '%s' and ltype = %d",
			dbesc($portable_id),
			intval($ltype)
		);
	}

}
