<?php

namespace Zotlabs\Module;

class Oauth2test extends \Zotlabs\Web\Controller {

	function get() {

		$o .= replace_macros(get_markup_template('oauth2test.tpl'), array(
			'$baseurl' => z_root(),
			'$endpoints' => array(
				array(
					'oauth2test',
					array(
						array(
							'action', 'create_db'
						)
					),
					'oauth2test_create_db',
					'Create the OAuth2 database tables'
				)
			)
		));

		return $o;
	}

	function post() {
		
		logger(json_encode($_POST), LOGGER_DEBUG);
		
		switch ($_POST['action']) {
			case 'create_db':
				logger('Creating database tables...', LOGGER_DEBUG);
				break;

			default:
				break;
		}
		
	}

}
