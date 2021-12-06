<?php

/**
 * @file Zotlabs/Module/Admin.php
 * @brief Hubzilla's admin controller.
 *
 * Controller for the /admin/ area.
 */

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Web\SubModule;
use Zotlabs\Lib\Config;

/**
 * @brief Admin area.
 *
 */
class Admin extends Controller
{

    private $sm = null;

    public function __construct()
    {
        $this->sm = new SubModule();
    }

    public function init()
    {

        logger('admin_init', LOGGER_DEBUG);

        if (!is_site_admin()) {
            logger('admin denied.');
            return;
        }

        if (argc() > 1) {
            $this->sm->call('init');
        }
    }


    public function post()
    {

        logger('admin_post', LOGGER_DEBUG);

        if (!is_site_admin()) {
            logger('admin denied.');
            return;
        }

        if (argc() > 1) {
            $this->sm->call('post');
        }

        // goaway(z_root() . '/admin' );
    }

    /**
     * @return string
     */

    public function get()
    {

        logger('admin_content', LOGGER_DEBUG);

        if (!is_site_admin()) {
            logger('admin denied.');
            return login(false);
        }

        /*
         * Page content
         */

        nav_set_selected('Admin');

        $o = '';

        if (argc() > 1) {
            $o = $this->sm->call('get');
            if ($o === false) {
                notice(t('Item not found.'));
            }
        } else {
            $o = $this->admin_page_summary();
        }

        if (is_ajax()) {
            echo $o;
            killme();
        } else {
            return $o;
        }
    }


    /**
     * @brief Returns content for Admin Summary Page.
     *
     * @return string HTML from parsed admin_summary.tpl
     */

    public function admin_page_summary()
    {

        // list total user accounts, expirations etc.
        $accounts = [];
        $r = q(
            "SELECT COUNT(CASE WHEN account_id > 0 THEN 1 ELSE NULL END) AS total, COUNT(CASE WHEN account_expires > %s THEN 1 ELSE NULL END) AS expiring, COUNT(CASE WHEN account_expires < %s AND account_expires > '%s' THEN 1 ELSE NULL END) AS expired, COUNT(CASE WHEN (account_flags & %d)>0 THEN 1 ELSE NULL END) AS blocked FROM account",
            db_utcnow(),
            db_utcnow(),
            dbesc(NULL_DATE),
            intval(ACCOUNT_BLOCKED)
        );
        if ($r) {
            $accounts['total'] = ['label' => t('Accounts'), 'val' => $r[0]['total']];
            $accounts['blocked'] = ['label' => t('Blocked accounts'), 'val' => $r[0]['blocked']];
            $accounts['expired'] = ['label' => t('Expired accounts'), 'val' => $r[0]['expired']];
            $accounts['expiring'] = ['label' => t('Expiring accounts'), 'val' => $r[0]['expiring']];
        }

        // pending registrations

        $pdg = q(
            "SELECT account.*, register.hash from account left join register on account_id = register.uid where (account_flags & %d ) > 0 ",
            intval(ACCOUNT_PENDING)
        );

        $pending = (($pdg) ? count($pdg) : 0);

        // available channels, primary and clones
        $channels = [];
        $r = q("SELECT COUNT(*) AS total, COUNT(CASE WHEN channel_primary = 1 THEN 1 ELSE NULL END) AS main, COUNT(CASE WHEN channel_primary = 0 THEN 1 ELSE NULL END) AS clones FROM channel WHERE channel_removed = 0 and channel_system = 0");
        if ($r) {
            $channels['total'] = ['label' => t('Channels'), 'val' => $r[0]['total']];
            $channels['main'] = ['label' => t('Primary'), 'val' => $r[0]['main']];
            $channels['clones'] = ['label' => t('Clones'), 'val' => $r[0]['clones']];
        }

        // We can do better, but this is a quick queue status
        $r = q("SELECT COUNT(outq_delivered) AS total FROM outq WHERE outq_delivered = 0");
        $queue = (($r) ? $r[0]['total'] : 0);
        $queues = ['label' => t('Message queues'), 'queue' => $queue];

        $plugins = [];

        if (is_array(App::$plugins) && App::$plugins) {
            foreach (App::$plugins as $p) {
                if ($p) {
                    $plugins[] = $p;
                }
            }
            sort($plugins);
        } else {
            $plugins = 0;
        }

        // Could be extended to provide also other alerts to the admin

        $alertmsg = '';

        $upgrade = EMPTY_STR;

        if ((!defined('PLATFORM_ARCHITECTURE')) || (PLATFORM_ARCHITECTURE === 'zap')) {
            $vrelease = get_repository_version('release');
            $vdev = get_repository_version('dev');
            $upgrade = ((version_compare(STD_VERSION, $vrelease) < 0) ? t('Your software should be updated') : '');
        }

        $t = get_markup_template('admin_summary.tpl');
        return replace_macros($t, [
            '$title' => t('Administration'),
            '$page' => t('Summary'),
            '$adminalertmsg' => $alertmsg,
            '$queues' => $queues,
            '$accounts' => [t('Registered accounts'), $accounts],
            '$pending' => [t('Pending registrations'), $pending],
            '$channels' => [t('Registered channels'), $channels],
            '$plugins' => (($plugins) ? [t('Active addons'), $plugins] : EMPTY_STR),
            '$version' => [t('Version'), STD_VERSION],
            '$vmaster' => [t('Repository version (release)'), $vrelease],
            '$vdev' => [t('Repository version (dev)'), $vdev],
            '$upgrade' => $upgrade,
            '$build' => Config::Get('system', 'db_version')
        ]);
    }
}
