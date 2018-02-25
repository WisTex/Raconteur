<?php

namespace Zotlabs\Module;

class OAuth2TestVehicle extends \Zotlabs\Web\Controller {

	function init() {
		
		// If there is a 'code' and 'state' parameter then this is a client app 
		// callback issued after the authorization code request
		// TODO: Check state value and compare to original sent value
			// "You should first compare this state value to ensure it matches the 
			// one you started with. You can typically store the state value in a 
			// cookie, and compare it when the user comes back. This ensures your 
			// redirection endpoint isn't able to be tricked into attempting to 
			// exchange arbitrary authorization codes."
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
							'action', 'delete_db'
						)
					),
					'oauth2test_delete_db',
					'Delete the OAuth2 database tables',
					'POST'
				),
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
					'authorize',
					array(
						array('response_type', 'code'),
						array('client_id', urlencode('killer_app')),
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
				),
				/*
				 * POST https://api.authorization-server.com/token
					grant_type=authorization_code&
					code=AUTH_CODE_HERE&
					redirect_uri=REDIRECT_URI&
					client_id=CLIENT_ID
				 */
				array(
					'oauth2testvehicle',
					array(
						array('action', 'request_token'),
						array('grant_type', 'authorization_code'),
						array('code', (x($_REQUEST, 'code') ? $_REQUEST['code'] : 'no_authorization_code')),
						array('redirect_uri', 'http://hub.localhost/oauth2testvehicle'),
						array('client_id', urlencode('killer_app')),
						array('client_secret', (x($_REQUEST, 'client_secret') ? $_REQUEST['client_secret'] : 'no_client_secret')),
					),
					'oauth_token_request',
					'Request a token',
					'POST'
				)
			)
		));

		return $o;
	}

	function post() {

		//logger(json_encode($_POST, JSON_PRETTY_PRINT), LOGGER_DEBUG);

		switch ($_POST['action']) {
			case 'request_token':
				$grant_type = (x($_POST, 'grant_type') ? $_POST['grant_type'] : '');
				$redirect_uri = (x($_POST, 'redirect_uri') ? $_POST['redirect_uri'] : '');
				$client_id = (x($_POST, 'client_id') ? $_POST['client_id'] : '');
				$code = (x($_POST, 'code') ? $_POST['code'] : '');
				$client_secret = (x($_POST, 'client_secret') ? $_POST['client_secret'] : '');
				$url = z_root() . '/token/?';
				$url .= 'grant_type=' . urlencode($grant_type);
				$url .= '&redirect_uri=' . urlencode($redirect_uri);
				$url .= '&client_id=' . urlencode($client_id);
				$url .= '&code=' . urlencode($code);
				$post = z_fetch_url($url, false, 0, array(
					'custom' => 'POST',
					'http_auth' => $client_id . ':' . $client_secret,
				));
				//logger(json_encode($post, JSON_PRETTY_PRINT), LOGGER_DEBUG);
				$response = json_decode($post['body'], true);
				logger(json_encode($response, JSON_PRETTY_PRINT), LOGGER_DEBUG);
				if($response['access_token']) {
					info('Access token received: ' . $response['access_token'] . EOL);
				}
				break;
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
