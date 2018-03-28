<?php

namespace Zotlabs\Module;

require_once('include/contact_widgets.php');
require_once('include/items.php');
require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');
require_once('include/permissions.php');

/**
 * @brief Channel Controller for broken OStatus implementations
 *
 */
class Ochannel extends \Zotlabs\Web\Controller {

	function init() {

		$which = null;
		if(argc() > 1)
			$which = argv(1);
		if(! $which) {
			if(local_channel()) {
				$channel = \App::get_channel();
				if($channel && $channel['channel_address'])
				$which = $channel['channel_address'];
			}
		}
		if(! $which) {
			notice( t('You must be logged in to see this page.') . EOL );
			return;
		}

		$profile = 0;
		$channel = \App::get_channel();

		if((local_channel()) && (argc() > 2) && (argv(2) === 'view')) {
			$which = $channel['channel_address'];
			$profile = argv(1);
		}

		head_add_link( [ 
			'rel'   => 'alternate', 
			'type'  => 'application/atom+xml',
			'href'  => z_root() . '/ofeed/' . $which
		]);


		// Run profile_load() here to make sure the theme is set before
		// we start loading content

		profile_load($which,$profile);
	}

	function get($update = 0, $load = false) {

		if(argc() < 2)
			return;

		if($load)
			$_SESSION['loadtime'] = datetime_convert();

		return '<script>window.location.href = "' . z_root() . '/' . str_replace('ochannel/','channel/',\App::$query_string) . '";</script>';

	}

}
