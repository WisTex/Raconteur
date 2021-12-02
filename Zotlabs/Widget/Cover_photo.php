<?php

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\System;

class Cover_photo {

	function widget($arr) {

		require_once('include/channel.php');
		$o = '';


		if(App::$module == 'channel' && $_REQUEST['mid'])
			return '';

		$channel_id = 0;

		$site_banner = false;

		if (App::$module === 'home') {
			$channel = get_sys_channel();
			$channel_id = $channel['channel_id'];
			$site_banner = System::get_site_name();
		}

		if(array_key_exists('channel_id', $arr) && intval($arr['channel_id']))
			$channel_id = intval($arr['channel_id']);
		if(! $channel_id)
			$channel_id = App::$profile_uid;
		if(! $channel_id)
			return '';

		// only show cover photos once per login session
		$hide_cover = false;
		if(array_key_exists('channels_visited',$_SESSION) && is_array($_SESSION['channels_visited']) && in_array($channel_id,$_SESSION['channels_visited'])) {
			$hide_cover = true;
		}
		if(! array_key_exists('channels_visited',$_SESSION)) {
			$_SESSION['channels_visited'] = [];
		}
		$_SESSION['channels_visited'][] = $channel_id;

		$channel = channelx_by_n($channel_id);

		if(array_key_exists('style', $arr) && isset($arr['style']))
			$style = $arr['style'];
		else
			$style = 'width:100%; height: auto;';

		// ensure they can't sneak in an eval(js) function

		if(strpbrk($style,'(\'"<>') !== false)
			$style = '';

		if(array_key_exists('title', $arr) && isset($arr['title']))
			$title = $arr['title'];
		else
			$title = $channel['channel_name'];


		if(array_key_exists('subtitle', $arr) && isset($arr['subtitle']))
			$subtitle = $arr['subtitle'];
		else
			$subtitle = str_replace('@','&#x40;',$channel['xchan_addr']);


		if ($site_banner) {
			$title = $site_banner;
			$subtitle = '';
		}

		$c = get_cover_photo($channel_id,'html');

		if($c) {
			$c = str_replace('src=', 'data-src=', $c);
			$photo_html = (($style) ? str_replace('alt=',' style="' . $style . '" alt=',$c) : $c);

			$o = replace_macros(get_markup_template('cover_photo_widget.tpl'),array(
				'$photo_html'	=> $photo_html,
				'$title'	=> $title,
				'$subtitle'	=> $subtitle,
				'$hovertitle' => t('Click to show more'),
				'$hide_cover' => $hide_cover
			));
		}
		return $o;
	}
}
