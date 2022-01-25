<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Apps;

class Settings_menu
{

    public function widget($arr)
    {

        if (!local_channel()) {
            return;
        }


        $channel = App::get_channel();

        $abook_self_id = 0;

        // Retrieve the 'self' address book entry for use in the auto-permissions link

        $role = get_pconfig(local_channel(), 'system', 'permissions_role');

        $abk = q(
            "select abook_id from abook where abook_channel = %d and abook_self = 1 limit 1",
            intval(local_channel())
        );
        if ($abk) {
            $abook_self_id = $abk[0]['abook_id'];
        }

        $x = q(
            "select count(*) as total from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 ",
            dbesc($channel['channel_hash'])
        );

        $hublocs = (($x && $x[0]['total'] > 1) ? true : false);

        $tabs = array(
            array(
                'label' => t('Account settings'),
                'url' => z_root() . '/settings/account',
                'selected' => ((argv(1) === 'account') ? 'active' : ''),
            ),

            array(
                'label' => t('Channel settings'),
                'url' => z_root() . '/settings/channel',
                'selected' => ((argv(1) === 'channel') ? 'active' : ''),
            ),

        );

        $tabs[] = array(
            'label' => t('Display settings'),
            'url' => z_root() . '/settings/display',
            'selected' => ((argv(1) === 'display') ? 'active' : ''),
        );

        $tabs[] = array(
            'label' => t('Manage Blocks'),
            'url' => z_root() . '/superblock',
            'selected' => ((argv(0) === 'superblock') ? 'active' : ''),
        );


        if ($hublocs) {
            $tabs[] = array(
                'label' => t('Manage locations'),
                'url' => z_root() . '/locs',
                'selected' => ((argv(1) === 'locs') ? 'active' : ''),
            );
        }

        $tabs[] = array(
            'label' => t('Export channel'),
            'url' => z_root() . '/uexport',
            'selected' => ''
        );

//      if(Features::enabled(local_channel(),'oauth_clients')) {
//          $tabs[] =   array(
//              'label' => t('OAuth1 apps'),
//              'url' => z_root() . '/settings/oauth',
//              'selected' => ((argv(1) === 'oauth') ? 'active' : ''),
//          );
//      }

        if (Apps::system_app_installed(local_channel(), 'Clients')) {
            $tabs[] = array(
                'label' => t('Client apps'),
                'url' => z_root() . '/settings/oauth2',
                'selected' => ((argv(1) === 'oauth2') ? 'active' : ''),
            );
        }

//      if(Features::enabled(local_channel(),'access_tokens')) {
//          $tabs[] =   array(
//              'label' => t('Guest Access Tokens'),
//              'url' => z_root() . '/settings/tokens',
//              'selected' => ((argv(1) === 'tokens') ? 'active' : ''),
//          );
//      }

      if(Apps::system_app_installed(local_channel(),'Roles')) {
          $tabs[] = array(
              'label' => t('Permission Roles'),
              'url' => z_root() . '/settings/permcats',
              'selected' => ((argv(1) === 'permcats') ? 'active' : ''),
          );
      }


//      if($role === false || $role === 'custom') {
//          $tabs[] = array(
//              'label' => t('Connection Default Permissions'),
//              'url' => z_root() . '/defperms',
//              'selected' => ''
//          );
//      }

//      if(Features::enabled(local_channel(),'channel_sources')) {
//          $tabs[] = array(
//              'label' => t('Channel Sources'),
//              'url' => z_root() . '/sources',
//              'selected' => ''
//          );
//      }

        $tabtpl = get_markup_template("generic_links_widget.tpl");
        return replace_macros($tabtpl, array(
            '$title' => t('Settings'),
            '$class' => 'settings-widget',
            '$items' => $tabs,
        ));
    }
}
