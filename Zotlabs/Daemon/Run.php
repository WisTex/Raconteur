<?php

namespace Zotlabs\Daemon;

if (array_search( __file__ , get_included_files()) === 0) {

	require_once('include/cli_startup.php');
	array_shift($argv);
	$argc = count($argv);

	if ($argc) {
		Run::Release($argc,$argv);
	}
	return;
}



class Run {

	static public function Summon($arr) {
		if (file_exists('maintenance_lock') || file_exists('cache/maintenance_lock')) {
			return;
		}
		proc_run('php','Zotlabs/Daemon/Run.php',$arr);
	}

	static public function Release($argc,$argv) {
		cli_startup();
		logger('Run: release: ' . print_r($argv,true), LOGGER_ALL,LOG_DEBUG);
		$cls = '\\Zotlabs\\Daemon\\' . $argv[0];
		$cls::run($argc,$argv);
	}	
}
