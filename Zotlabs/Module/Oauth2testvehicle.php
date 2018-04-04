<?php

namespace Zotlabs\Module;

/**
 * The OAuth2TestVehicle class is a way to test the registration of an OAuth2
 * client app. It allows you to walk through the steps of registering a client,
 * requesting an authorization code for that client, and then requesting an 
 * access token for use in authentication against the Hubzilla API endpoints.
 */
class OAuth2TestVehicle extends \Zotlabs\Web\Controller {

	function init() {

		killme();
		
		// If there is a 'code' and 'state' parameter then this is a client app 
		// callback issued after the authorization code request
		// TODO: Check state value and compare to original sent value
			// "You should first compare this state value to ensure it matches the 
			// one you started with. You can typically store the state value in a 
			// cookie, and compare it when the user comes back. This ensures your 
			// redirection endpoint isn't able to be tricked into attempting to 
			// exchange arbitrary authorization codes."
		$_SESSION['redirect_uri'] = z_root() . '/oauth2testvehicle';
		$_SESSION['authorization_code'] = (x($_REQUEST, 'code') ? $_REQUEST['code'] : $_SESSION['authorization_code']);
		$_SESSION['state'] = (x($_REQUEST, 'state') ? $_REQUEST['state'] : $_SESSION['state'] );
		$_SESSION['client_id'] = (x($_REQUEST, 'client_id') ? $_REQUEST['client_id'] : $_SESSION['client_id'] );
		$_SESSION['client_secret'] = (x($_REQUEST, 'client_secret') ? $_REQUEST['client_secret'] : $_SESSION['client_secret']);
		$_SESSION['access_token'] = (x($_REQUEST, 'access_token') ? $_REQUEST['access_token'] : $_SESSION['access_token'] );
		$_SESSION['api_response'] = (x($_SESSION, 'api_response') ? $_SESSION['api_response'] : '');
	}
	function get() {
		
		$o .= replace_macros(get_markup_template('oauth2testvehicle.tpl'), array(
			'$baseurl' => z_root(),
			'$api_response' => $_SESSION['api_response'],
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
					'authorize',
					array(
						array('response_type', 'code'),
						array('client_id', (x($_REQUEST, 'client_id') ? $_REQUEST['client_id'] : 'oauth2_test_app')),
						array('redirect_uri', $_SESSION['redirect_uri']),
						array('state', 'xyz'),
						// OpenID Connect Dynamic Client Registration 1.0 Client Metadata
						// http://openid.net/specs/openid-connect-registration-1_0.html
						array('client_name', 'OAuth2 Test App'),
						array('logo_uri', urlencode(z_root() . '/images/icons/plugin.png')),
						array('client_uri', urlencode('https://client.example.com/website')),
						array('application_type', 'web'), // would be 'native' for mobile app
					),
					'oauth_authorize',
					'Authorize a test client app',
					'GET',
					(($_REQUEST['code'] && $_REQUEST['state']) ? true : false),
				),
				array(
					'oauth2testvehicle',
					array(
						array('action', 'request_token'),
						array('grant_type', 'authorization_code'),
						array('code', $_SESSION['authorization_code']),
						array('redirect_uri', $_SESSION['redirect_uri']),
						array('client_id', ($_SESSION['client_id'] ? $_SESSION['client_id'] : 'oauth2_test_app')),
						array('client_secret', $_SESSION['client_secret']),
					),
					'oauth_token_request',
					'Request a token',
					'POST',
					($_SESSION['success'] === 'request_token'),
				),
				array(
					'oauth2testvehicle',
					array(
						array('action', 'api_files'),
						array('access_token', $_SESSION['access_token']),
					),
					'oauth_api_files',
					'API: Get channel files',
					'POST',
					($_SESSION['success'] === 'api_files'),
				)
			)
		));
		$_SESSION['success'] = '';
		return $o;
	}

	function post() {

		switch ($_POST['action']) {
			case 'api_files':
				$access_token = $_SESSION['access_token'];
				$url = z_root() . '/api/z/1.0/files/';				
				$headers = [];
				$headers[] = 'Authorization: Bearer ' . $access_token;
				$post = z_fetch_url($url, false, 0, array(
					'custom' => 'GET',
					'headers' => $headers,
				));
				logger(json_encode($post, JSON_PRETTY_PRINT), LOGGER_DEBUG);
				$response = json_decode($post['body'], true);
				$_SESSION['api_response'] = json_encode($response, JSON_PRETTY_PRINT);
				break;
			case 'request_token':
				$grant_type = (x($_POST, 'grant_type') ? $_POST['grant_type'] : '');
				$redirect_uri = (x($_POST, 'redirect_uri') ? $_POST['redirect_uri'] : '');
				$client_id = (x($_POST, 'client_id') ? $_POST['client_id'] : '');
				$code = (x($_POST, 'code') ? $_POST['code'] : '');
				$client_secret = (x($_POST, 'client_secret') ? $_POST['client_secret'] : '');
				$url = z_root() . '/token/';
				$params = http_build_query(array(
					'grant_type' => $grant_type,
					'redirect_uri' => urlencode($redirect_uri),
					'client_id' => $client_id,
					'code' => $code,
				));
				$post = z_post_url($url, $params, 0, array(
					'http_auth' => $client_id . ':' . $client_secret,
				));
				logger(json_encode($post, JSON_PRETTY_PRINT), LOGGER_DEBUG);
				$response = json_decode($post['body'], true);
				logger(json_encode($response, JSON_PRETTY_PRINT), LOGGER_DEBUG);
				if($response['access_token']) {
					info('Access token received: ' . $response['access_token'] . EOL);
					$_SESSION['success'] = 'request_token';
					$_SESSION['access_token'] = $response['access_token'];
				}
				break;

			default:
				break;
		}
	}

}
