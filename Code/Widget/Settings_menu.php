<?php

namespace Code\Widget;

use App;
use Code\Lib\Apps;
use Code\Render\Theme;


class Settings_menu implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        if (!local_channel()) {
            return '';
        }


        $channel = App::get_channel();

        $x = q(
            "select count(*) as total from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 ",
            dbesc($channel['channel_hash'])
        );

        $hublocs = $x && $x[0]['total'] > 1;

        $tabs = [
            [
                'label' => t('Account settings'),
                'url' => z_root() . '/settings/account',
                'selected' => ((argv(1) === 'account') ? 'active' : ''),
            ],

            [
                'label' => t('Channel settings'),
                'url' => z_root() . '/settings/channel',
                'selected' => ((argv(1) === 'channel') ? 'active' : ''),
            ],

        ];

        $tabs[] = [
            'label' => t('Edit profile'),
            'url' => z_root() . '/settings/profile_edit',
            'selected' => ((argv(1) === 'profile_edit') ? 'active' : ''),
        ];

        $tabs[] = [
            'label' => t('Display settings'),
            'url' => z_root() . '/settings/display',
            'selected' => ((argv(1) === 'display') ? 'active' : ''),
        ];

        $tabs[] = [
            'label' => t('Manage blocked sites/channels'),
            'url' => z_root() . '/superblock',
            'selected' => ((argv(0) === 'superblock') ? 'active' : ''),
        ];


        if ($hublocs) {
            $tabs[] = [
                'label' => t('Manage locations'),
                'url' => z_root() . '/locs',
                'selected' => ((argv(1) === 'locs') ? 'active' : ''),
            ];
        }

        $tabs[] = [
            'label' => t('Export channel'),
            'url' => z_root() . '/uexport',
            'selected' => ''
        ];

        $tabs[] = [
            'label' => t('Multifactor authentication'),
            'url' => z_root() . '/settings/multifactor',
            'selected' => ((argv(1) === 'multifactor') ? 'active' : ''),
        ];

        if (Apps::system_app_installed(local_channel(), 'Clients')) {
            $tabs[] = [
                'label' => t('Client apps'),
                'url' => z_root() . '/settings/oauth2',
                'selected' => ((argv(1) === 'oauth2') ? 'active' : ''),
            ];
        }

        if(Apps::system_app_installed(local_channel(),'Roles')) {
          $tabs[] = [
              'label' => t('Permission roles'),
              'url' => z_root() . '/settings/permcats',
              'selected' => ((argv(1) === 'permcats') ? 'active' : ''),
          ];
        }

        return replace_macros(Theme::get_template('generic_links_widget.tpl'), [
            '$title' => t('Settings'),
            '$class' => 'settings-widget',
            '$items' => $tabs,
        ]);
    }
}
