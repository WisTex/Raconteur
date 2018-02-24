<?php

namespace Zotlabs\Module;

class OAuth2TestVehicle extends \Zotlabs\Web\Controller {

	function init() {
		
		// If there is a 'code' and 'state' parameter then this is a client app 
		// callback issued after the authorization code request
		// TODO: Check state value and compare to original sent value
		if ($_REQUEST['code'] && $_REQUEST['state']) {
			logger('Authorization callback invoked.', LOGGER_DEBUG);
			logger(json_encode($_REQUEST, JSON_PRETTY_PRINT), LOGGER_DEBUG);
			info('Authorization callback invoked.' . EOL);
			return $this->get();
		}
	}
	function get() {

		$o .= replace_macros(get_markup_template('oauth2testvehicle.tpl'), array(
			'$baseurl' => z_root(),
			/*
			  endpoints => array(
			  array(
			  'path_to_endpoint',
			  array(
			  array('field_name_1', 'value'),
			  array('field_name_2', 'value'),
			  ...
			  ),
			  'submit_button_name',
			  'Description of API action'
			  )
			  )
			 */
			'$endpoints' => array(
				array(
					'oauth2testvehicle',
					array(
						array(
							'action', 'create_db'
						)
					),
					'oauth2test_create_db',
					'Create the OAuth2 database tables',
					'POST'
				),
				array(
					'oauth2testvehicle',
					array(
						array(
							'action', 'delete_db'
						)
					),
					'oauth2test_delete_db',
					'Delete the OAuth2 database tables',
					'POST'
				),
				array(
					'authorize',
					array(
						array('response_type', 'code'),
						array('client_id', urlencode('test_app_client_id')),
						array('redirect_uri', 'http://hub.localhost/oauth2testvehicle'),
						array('state', 'xyz'),
						// OpenID Connect Dynamic Client Registration 1.0 Client Metadata
						// http://openid.net/specs/openid-connect-registration-1_0.html
						array('client_name', urlencode('Killer App')),
						array('logo_uri', urlencode('https://client.example.com/website/img/icon.png')),
						array('client_uri', urlencode('https://client.example.com/website')),
						array('application_type', 'web'), // would be 'native' for mobile app
					),
					'oauth_authorize',
					'Authorize a test client app',
					'GET'
				)
			)
		));

		return $o;
	}

	function post() {

		logger(json_encode($_POST), LOGGER_DEBUG);


		switch ($_POST['action']) {

			case 'delete_db':
				$status = true;
				// Use the \OAuth2\Storage\Pdo class to create the OAuth2 tables
				// by passing it the database connection 
				$pdo = \DBA::$dba->db;
				$storage = new \Zotlabs\Storage\ZotOauth2Pdo($pdo);
				logger('Deleting existing database tables...', LOGGER_DEBUG);
				foreach ($storage->getConfig() as $key => $table) {
					logger('Deleting table ' . dbesc($table), LOGGER_DEBUG);
					$r = q("DROP TABLE %s;", dbesc($table));
					if (!$r) {
						logger('Errors encountered deleting database table ' . $table . '.', LOGGER_DEBUG);
						$status = false;
					}
				}
				if (!$status) {
					notice('Errors encountered deleting database tables.' . EOL);
				} else {
					info('Database tables deleted successfully.' . EOL);
				}

				break;

			case 'create_db':
				$status = true;
				logger('Creating database tables...', LOGGER_DEBUG);
				@include('.htconfig.php');
				$pdo = \DBA::$dba->db;
				$storage = new \Zotlabs\Storage\ZotOauth2Pdo($pdo);
				foreach (explode(';', $storage->getBuildSql($db_data)) as $statement) {
					try {
						$result = $pdo->exec($statement);
					} catch (\PDOException $e) {
						$status = false;
						logger('Error executing database statement: ' . $statement, LOGGER_DEBUG);
					}
				}

				if (!$status) {
					notice('Errors encountered creating database tables.' . EOL);
				} else {
					info('Database tables created successfully.' . EOL);
				}

			default:
				break;
		}
	}

}
