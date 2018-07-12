<?php

namespace Zotlabs\Extend;


class Widget {

	static function register($file,$widget) {
		$rt = self::get();
		$rt[] = [ $file, $widget ];
		self::set($rt);
	}

	static function unregister($file,$widget) {
		$rt = self::get();
		if($rt) {
			$n = [];
			foreach($rt as $r) {
				if($r[0] !== $file && $r[1] !== $widget) {
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
		return get_config('system','widgets',[]);
	}

	static function set($r) {
		return set_config('system','widgets',$r);
	}
}
