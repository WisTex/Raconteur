<?php

namespace Zotlabs\Lib;


class Api_router {

	static private $routes = array();

	static function register($path,$fn,$auth_required) {
		self::$routes[$path] = [ 'func' => $fn, 'auth' => $auth_required ];
	}

	static function find($path) {
		if (array_key_exists($path,self::$routes)) {
			return self::$routes[$path];
		}

		$with_params = dirname($path) . '/[id]';

		if (array_key_exists($with_params,self::$routes)) {
			return self::$routes[$with_params];
		}

		return null;
	}

	static function dbg() {
		return self::$routes;
	}

}