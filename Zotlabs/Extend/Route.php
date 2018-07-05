<?php

namespace Zotlabs\Extend;


class Route {

	static function register($file,$modname) {
		$rt = self::get();
		$rt[] = [ $file, $modname ];
		self::set($rt);
	}

	static function unregister($file,$modname) {
		$rt = self::get();
		if($rt) {
			$n = [];
			foreach($rt as $r) {
				if($r[0] !== $file && $r[1] !== $modname) {
					$n[] = $r;
				}
			}
			self::set($n);
		}
	}

	static function unregister_by_file($file) {
		$rt = self::get();
		if($rt) {
			$n = [];
			foreach($rt as $r) {
				if($r[0] !== $file) {
					$n[] = $r;
				}
			}
			self::set($n);
		}
	}

	static function get() {
		return get_config('system','routes',[]);
	}

	static function set($r) {
		return set_config('system','routes',$r);
	}
}

