<?php

namespace Code\Widget;

use App;
use Code\Extend\Hook;
use Code\Render\Theme;


class Admin implements WidgetInterface
{

    public function widget(array $arr): string
    {

        /*
         * Side bar links
         */

        if (!is_site_admin()) {
            return '';
        }

        $o = '';

        // array( url, name, extra css classes )

        $aside = [
            'site' => [z_root() . '/admin/site/', t('Site'), 'site'],
//          'profile_photo' => array(z_root() . '/admin/profile_photo', t('Site icon/logo'), 'profile_photo'),
//          'cover_photo'   => array(z_root() . '/admin/cover_photo', t('Site photo'), 'cover_photo'),
            'accounts' => [z_root() . '/admin/accounts/', t('Accounts'), 'accounts', 'pending-update', t('Member registrations waiting for confirmation')],
            'channels' => [z_root() . '/admin/channels/', t('Channels'), 'channels'],
            'security' => [z_root() . '/admin/security/', t('Security'), 'security'],
//          'features'      => array(z_root() . '/admin/features/', t('Features'),       'features'),
            'addons' => [z_root() . '/admin/addons/', t('Addons'), 'addons'],
            'themes' => [z_root() . '/admin/themes/', t('Themes'), 'themes'],
            'queue' => [z_root() . '/admin/queue', t('Inspect queue'), 'queue'],
//          'profs'         => array(z_root() . '/admin/profs',     t('Profile Fields'), 'profs'),
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

        $arr = ['links' => $aside, 'plugins' => $plugins, 'logs' => $logs];
        Hook::call('admin_aside', $arr);

        $o .= replace_macros(Theme::get_template('admin_aside.tpl'), [
            '$admin' => $arr['links'],
            '$admtxt' => t('Admin'),
            '$plugadmtxt' => t('Addon Features'),
            '$plugins' => $arr['plugins'],
            '$logtxt' => t('Logs'),
            '$logs' => $arr['logs'],
            '$h_pending' => t('Member registrations waiting for confirmation'),
            '$admurl' => z_root() . '/admin/'
        ]);

        return $o;
    }
}
