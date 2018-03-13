<?php

namespace Zotlabs\Module;


class Go extends \Zotlabs\Web\Controller {

	function init() {
		if(local_channel()) {
			$channel = \App::get_channel();
			if($channel) {
				profile_load($channel['channel_address'],0);
			}
		}
	}



	function get() {
		if(! local_channel()) {
			notify( t('This page is available only to site members') . EOL);
		}

		$channel = \App::get_channel();


		$title = t('Welcome');

		$m = t('What would you like to do?');

		$m1 = t('Please bookmark this page if you would like to return to it in the future');


		$options = [
			'profile_photo' => t('Upload a profile photo'),
			'cover_photo'   => t('Upload a cover photo'),
			'profiles'      => t('Edit your default profile'),
			'suggest'       => t('View friend suggestions'),
			'directory'     => t('View the channel directory'),
			'settings'      => t('View/edit your channel settings'),
			'help'          => t('View the site or project documentation'),
			'channel/' . $channel['channel_address']       => t('Visit your channel homepage'),
			'connections'   => t('View your connections and/or add somebody whose address you already know'),
			'network'       => t('View your personal stream (this may be empty until you add some connections)'),

		];
 
		$site_firehose = ((intval(get_config('system','site_firehose',0))) ? true : false);
 		$net_firehose  = ((get_config('system','disable_discover_tab',1)) ? false : true);

		if($site_firehose || $net_firehose) {
			$options['pubstream'] = t('View the public stream. Warning: this content is not moderated');
		}

		$o = replace_macros(get_markup_template('go.tpl'), [
			'$title' => $title,
			'$m' => $m,
			'$m1' => $m1,
			'$options' => $options

		]);

		return $o;

	}

}