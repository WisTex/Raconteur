<?php

namespace Zotlabs\Module;

use Zotlabs\Identity\OAuth2Storage;

class Authorize extends \Zotlabs\Web\Controller {

	function get() {
		if (!local_channel()) {
			return login();
		} else {
			// TODO: Fully implement the dynamic client registration protocol:
			// OpenID Connect Dynamic Client Registration 1.0 Client Metadata
			// http://openid.net/specs/openid-connect-registration-1_0.html
			$app = array(
				'name' => (x($_REQUEST, 'client_name') ? urldecode($_REQUEST['client_name']) : t('Unknown App')),
				'icon' => (x($_REQUEST, 'logo_uri')    ? urldecode($_REQUEST['logo_uri']) : z_root() . '/images/icons/plugin.png'),
				'url'  => (x($_REQUEST, 'client_uri')  ? urldecode($_REQUEST['client_uri']) : ''),
			);
			$o .= replace_macros(get_markup_template('oauth_authorize.tpl'), array(
				'$title' => t('Authorize'),
				'$authorize' => sprintf( t('Do you authorize the app %s to access your channel data?'), '<a style="float: none;" href="' . $app['url'] . '">' . $app['name'] . '</a> '),
				'$app' => $app,
				'$yes' => t('Allow'),
				'$no' => t('Deny'),
				'$client_id' => (x($_REQUEST, 'client_id') ? $_REQUEST['client_id'] : ''),
				'$redirect_uri' => (x($_REQUEST, 'redirect_uri') ? $_REQUEST['redirect_uri'] : ''),
				'$state' => (x($_REQUEST, 'state') ? $_REQUEST['state'] : ''),
			));
			return $o;
		}
	}

	function post() {
		if (! local_channel()) {
			return;
		}

		$storage = new OAuth2Storage(\DBA::$dba->db);
		$s = new \Zotlabs\Identity\OAuth2Server($storage);

		// TODO: The automatic client registration protocol below should adhere more
		// closely to "OAuth 2.0 Dynamic Client Registration Protocol" defined
		// at https://tools.ietf.org/html/rfc7591
		
		// If no client_id was provided, generate a new one.
		if (x($_POST, 'client_id')) {
			$client_id = $_POST['client_id'];
		} else {
			$client_id = $_POST['client_id'] = random_string(16);
		}
		// If no redirect_uri was provided, generate a fake one.
		if (x($_POST, 'redirect_uri')) {
			$redirect_uri = $_POST['redirect_uri'];
		} else {
			$redirect_uri = $_POST['redirect_uri'] = 'https://fake.example.com/oauth';
		}

		$request = \OAuth2\Request::createFromGlobals();
		$response = new \OAuth2\Response();

		// If the client is not registered, add to the database
		if (!$client = $storage->getClientDetails($client_id)) {
			$client_secret = random_string(16);
			// Client apps are registered per channel
			$user_id = local_channel();
			$storage->setClientDetails($client_id, $client_secret, $redirect_uri, 'authorization_code', null, $user_id);
			
		}
		if (!$client = $storage->getClientDetails($client_id)) {
			// There was an error registering the client.
			$response->send();
			killme();
		}
		$response->setParameter('client_secret', $client['client_secret']);

		// validate the authorize request
		if (!$s->validateAuthorizeRequest($request, $response)) {
			$response->send();
			killme();
		}

		// print the authorization code if the user has authorized your client
		$is_authorized = ($_POST['authorize'] === 'allow');
		$s->handleAuthorizeRequest($request, $response, $is_authorized, local_channel());
		if ($is_authorized) {
			$code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=') + 5, 40);
			logger('Authorization Code: ' .  $code);
		}

		$response->send();
		killme();
	}

}
