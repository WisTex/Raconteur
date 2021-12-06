<?php

namespace Zotlabs\Daemon;

if (array_search(__file__, get_included_files()) === 0) {
    require_once('include/cli_startup.php');
    array_shift($argv);
    $argc = count($argv);

    if ($argc) {
        Run::Release($argc, $argv);
    }
    return;
}



class Run
{

    // These processes should be ignored by addons which enforce timeouts (e.g. queueworker)
    // as it could result in corrupt data. Please add additional long running tasks to this list as they arise.
    // Ideally the queueworker should probably be provided an allow list rather than a deny list as it will be easier
    // to maintain. This was a quick hack to fix truncation of very large synced files when the queueworker addon is installed.

    public static $long_running = [ 'Addon', 'Channel_purge', 'Checksites', 'Content_importer', 'Convo',
        'Cron', 'Cron_daily', 'Cron_weekly', 'Delxitems', 'Expire', 'File_importer', 'Importfile'
    ];

    public static function Summon($arr)
    {
        if (file_exists('maintenance_lock') || file_exists('cache/maintenance_lock')) {
            return;
        }

        $hookinfo = [
            'argv' => $arr,
            'long_running' => self::$long_running
        ];

        call_hooks('daemon_summon', $hookinfo);

        $arr  = $hookinfo['argv'];
        $argc = count($arr);

        if ((! is_array($arr) || ($argc < 1))) {
            logger("Summon handled by hook.", LOGGER_DEBUG);
            return;
        }

        proc_run('php', 'Zotlabs/Daemon/Run.php', $arr);
    }

    public static function Release($argc, $argv)
    {
        cli_startup();

        $hookinfo = [
            'argv' => $argv,
            'long_running' => self::$long_running
        ];

        call_hooks('daemon_release', $hookinfo);

        $argv = $hookinfo['argv'];
        $argc = count($argv);

        if ((! is_array($argv) || ($argc < 1))) {
            logger("Release handled by hook.", LOGGER_DEBUG);
            return;
        }

        logger('Run: release: ' . print_r($argv, true), LOGGER_ALL, LOG_DEBUG);
        $cls = '\\Zotlabs\\Daemon\\' . $argv[0];
        $cls::run($argc, $argv);
    }
}
