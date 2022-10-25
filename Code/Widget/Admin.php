<?php

namespace Code\Widget;

use App;
use Code\Extend\Hook;
use Code\Render\Theme;


class Admin implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        /*
         * Side bar links
         */

        if (!is_site_admin()) {
            return '';
        }

        $o = '';

        $aside = [
            'site' => [z_root() . '/admin/site/', t('Site'), 'site'],
            'accounts' => [z_root() . '/admin/accounts/', t('Accounts'), 'accounts', 'pending-update', t('Member registrations waiting for confirmation')],
            'channels' => [z_root() . '/admin/channels/', t('Channels'), 'channels'],
            'security' => [z_root() . '/admin/security/', t('Security'), 'security'],
            'addons' => [z_root() . '/admin/addons/', t('Addons'), 'addons'],
            'themes' => [z_root() . '/admin/themes/', t('Themes'), 'themes'],
            'queue' => [z_root() . '/admin/queue', t('Inspect queue'), 'queue'],
            'dbsync' => [z_root() . '/admin/dbsync/', t('DB updates'), 'dbsync']
        ];

        /* get plugins admin page */

        $r = q("SELECT * FROM addon WHERE plugin_admin = 1");

        $plugins = [];
        if ($r) {
            foreach ($r as $h) {
                $plugin = $h['aname'];
                $plugins[] = [z_root() . '/admin/addons/' . $plugin, $plugin, 'plugin'];
                // temp plugins with admin
                App::$addons_admin[] = $plugin;
            }
        }

        $logs = [z_root() . '/admin/logs/', t('Logs'), 'logs'];

        $arguments = ['links' => $aside, 'plugins' => $plugins, 'logs' => $logs];
        Hook::call('admin_aside', $arguments);

        return replace_macros(Theme::get_template('admin_aside.tpl'), [
            '$admin' => $arguments['links'],
            '$admtxt' => t('Admin'),
            '$plugadmtxt' => t('Addon Features'),
            '$plugins' => $arguments['plugins'],
            '$logtxt' => t('Logs'),
            '$logs' => $arguments['logs'],
            '$h_pending' => t('Member registrations waiting for confirmation'),
            '$admurl' => z_root() . '/admin/'
        ]);

    }
}
