<?php
namespace Zotlabs\Daemon;

/* This file will be left here for a period of six months
 * starting 2020-06-15. Then it will be removed.
 * The English word 'master' is being removed from the codebase.
 * This file was previously referenced in setup scripts and is
 * required to run background tasks. It is executed by cron.
 * We don't want to break this on the short term. This is your
 * first warning. The file will be removed in six months and
 * you will need to point your cron job or scheduled task to
 * Zotlabs/Daemon/Run.php instead of Zotlabs/Daemon/Master.php.
 * 
 */

if(array_search( __file__ , get_included_files()) === 0) {

	require_once('include/cli_startup.php');
	array_shift($argv);
	$argc = count($argv);

	if($argc)
		Master::Release($argc,$argv);
	return;
}



class Master {

	static public function Summon($arr) {
		proc_run('php','Zotlabs/Daemon/Master.php',$arr);
	}

	static public function Release($argc,$argv) {
		cli_startup();
		logger('Master: release: ' . print_r($argv,true), LOGGER_ALL,LOG_DEBUG);
		$cls = '\\Zotlabs\\Daemon\\' . $argv[0];
		$cls::run($argc,$argv);
	}	
}
