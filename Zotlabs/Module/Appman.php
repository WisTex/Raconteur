<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Apps;

class Appman extends Controller
{

    public function post()
    {

		$channel_id = local_channel();
        if (! $channel_id) {
            return;
        }
		if (is_sys_channel($channel_id)) {
			$channel_id = 0;
		}

        if ($_POST['url']) {
            $arr = [
                'uid' => intval($_REQUEST['uid']),
                'url' => escape_tags($_REQUEST['url']),
                'guid' => escape_tags($_REQUEST['guid']),
                'author' => escape_tags($_REQUEST['author']),
                'addr' => escape_tags($_REQUEST['addr']),
                'name' => escape_tags($_REQUEST['name']),
                'desc' => escape_tags($_REQUEST['desc']),
                'photo' => escape_tags($_REQUEST['photo']),
                'version' => escape_tags($_REQUEST['version']),
                'price' => escape_tags($_REQUEST['price']),
                'page' => escape_tags($_REQUEST['sellpage']), // do not use 'page' as a request variable here as it conflicts with pagination
                'requires' => escape_tags($_REQUEST['requires']),
                'system' => intval($_REQUEST['system']),
                'plugin' => escape_tags($_REQUEST['plugin']),
                'sig' => escape_tags($_REQUEST['sig']),
                'categories' => escape_tags($_REQUEST['categories'])
            ];

            $_REQUEST['appid'] = Apps::app_install($channel_id, $arr);

            if (Apps::app_installed($channel_id, $arr)) {
                info(t('App installed.') . EOL);
            }

            goaway(z_root() . '/apps');
        }


        $papp = Apps::app_decode($_POST['papp']);

        if (!is_array($papp)) {
            notice(t('Malformed app.') . EOL);
            return;
        }

        if ($_POST['install']) {
            Apps::app_install($channel_id, $papp);
            if (Apps::app_installed($channel_id, $papp)) {
                info(t('App installed.') . EOL);
            }
        }

        if ($_POST['delete']) {
            Apps::app_destroy($channel_id, $papp);
        }

        if ($_POST['edit']) {
            return;
        }

        if ($_POST['feature']) {
            Apps::app_feature($channel_id, $papp, $_POST['feature']);
        }

        if ($_POST['pin']) {
            Apps::app_feature($channel_id, $papp, $_POST['pin']);
        }

        if ($_SESSION['return_url']) {
            goaway(z_root() . '/' . $_SESSION['return_url']);
        }

        goaway(z_root() . '/apps');
    }


    public function get()
    {

		$channel_id = local_channel();
		
        if (!$channel_id) {
            notice(t('Permission denied.') . EOL);
            return;
        }

		if (is_sys_channel($channel_id)) {
			$channel_id = 0;
		}
		
        $channel = App::get_channel();

        if (argc() > 3) {
            if (argv(2) === 'moveup') {
                Apps::moveup($channel_id, argv(1), argv(3));
            }
            if (argv(2) === 'movedown') {
                Apps::movedown($channel_id, argv(1), argv(3));
            }
            goaway(z_root() . '/apporder');
        }

        $app = null;
        $embed = null;
        if ($_REQUEST['appid']) {
            $r = q(
                "select * from app where app_id = '%s' and app_channel = %d limit 1",
                dbesc($_REQUEST['appid']),
                dbesc($channel_id)
            );
            if ($r) {
                $app = $r[0];

                $term = q(
                    "select * from term where otype = %d and oid = %d and uid = %d",
                    intval(TERM_OBJ_APP),
                    intval($r[0]['id']),
                    intval($channel_id)
                );
                if ($term) {
                    $app['categories'] = array_elm_to_str($term, 'term');
                }
            }

            $embed = ['embed', t('Embed code'), Apps::app_encode($app, true), EMPTY_STR, 'onclick="this.select();"'];
        }

        return replace_macros(get_markup_template('app_create.tpl'), [
            '$banner' => (($app) ? t('Edit App') : t('Create App')),
            '$app' => $app,
            '$guid' => (($app) ? $app['app_id'] : EMPTY_STR),
            '$author' => (($app) ? $app['app_author'] : $channel['channel_hash']),
            '$addr' => (($app) ? $app['app_addr'] : $channel['xchan_addr']),
            '$name' => ['name', t('Name of app'), (($app) ? $app['app_name'] : EMPTY_STR), t('Required')],
            '$url' => ['url', t('Location (URL) of app'), (($app) ? $app['app_url'] : EMPTY_STR), t('Required')],
            '$desc' => ['desc', t('Description'), (($app) ? $app['app_desc'] : EMPTY_STR), EMPTY_STR],
            '$photo' => ['photo', t('Photo icon URL'), (($app) ? $app['app_photo'] : EMPTY_STR), t('80 x 80 pixels - optional')],
            '$categories' => ['categories', t('Categories (optional, comma separated list)'), (($app) ? $app['categories'] : EMPTY_STR), EMPTY_STR],
            '$version' => ['version', t('Version ID'), (($app) ? $app['app_version'] : EMPTY_STR), EMPTY_STR],
            '$price' => ['price', t('Price of app'), (($app) ? $app['app_price'] : EMPTY_STR), EMPTY_STR],
            '$page' => ['sellpage', t('Location (URL) to purchase app'), (($app) ? $app['app_page'] : EMPTY_STR), EMPTY_STR],
            '$system' => (($app) ? intval($app['app_system']) : 0),
            '$plugin' => (($app) ? $app['app_plugin'] : EMPTY_STR),
            '$requires' => (($app) ? $app['app_requires'] : EMPTY_STR),
            '$embed' => $embed,
            '$submit' => t('Submit')
        ]);
    }
}
