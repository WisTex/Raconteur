<?php

namespace Zotlabs\Module;

use Zotlabs\Identity\OAuth2Storage;

class Authorize extends \Zotlabs\Web\Controller {

	function init() {

		// workaround for HTTP-auth in CGI mode
		if (x($_SERVER, 'REDIRECT_REMOTE_USER')) {
			$userpass = base64_decode(substr($_SERVER["REDIRECT_REMOTE_USER"], 6));
			if (strlen($userpass)) {
				list($name, $password) = explode(':', $userpass);
				$_SERVER['PHP_AUTH_USER'] = $name;
				$_SERVER['PHP_AUTH_PW'] = $password;
			}
		}

		if (x($_SERVER, 'HTTP_AUTHORIZATION')) {
			$userpass = base64_decode(substr($_SERVER["HTTP_AUTHORIZATION"], 6));
			if (strlen($userpass)) {
				list($name, $password) = explode(':', $userpass);
				$_SERVER['PHP_AUTH_USER'] = $name;
				$_SERVER['PHP_AUTH_PW'] = $password;
			}
		}
	}

	function get() {
		if (!local_channel()) {
			return login();
		} else {
			// TODO: Fully implement the dynamic client registration protocol:
			// OpenID Connect Dynamic Client Registration 1.0 Client Metadata
			// http://openid.net/specs/openid-connect-registration-1_0.html
			$app = array(
				'name' => (x($_REQUEST, 'client_name') ? urldecode($_REQUEST['client_name']) : 'Unknown App'),
				'icon' => (x($_REQUEST, 'logo_uri') ? urldecode($_REQUEST['logo_uri']) : '/images/icons/plugin.png'),
				'url' => (x($_REQUEST, 'client_uri') ? urldecode($_REQUEST['client_uri']) : ''),
			);
			$o .= replace_macros(get_markup_template('oauth_authorize.tpl'), array(
				'$title' => '',
				'$authorize' => 'Do you authorize the app <a style="float: none;" href="' . $app['url'] . '">' . $app['name'] . '</a> to access your channel data?',
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
		if (!local_channel()) {
			return $this->get();
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
			$redirect_uri = $_POST['redirect_uri'] = 'https://fake.example.com';
		}

		// If the client is not registered, add to the database
		if (!$storage->getClientDetails($client_id)) {
			$client_secret = random_string(16);
			// Client apps are registered per channel
			$user_id = local_channel();
			$storage->setClientDetails($client_id, $client_secret, $redirect_uri, null, null, $user_id);
		}

		$request = \OAuth2\Request::createFromGlobals();
		$response = new \OAuth2\Response();

		// validate the authorize request
		if (!$s->validateAuthorizeRequest($request, $response)) {
			$response->send();
			killme();
		}

		// print the authorization code if the user has authorized your client
		$is_authorized = ($_POST['authorize'] === 'allow');
		$s->handleAuthorizeRequest($request, $response, $is_authorized, local_channel());
		if ($is_authorized) {
			// this is only here so that you get to see your code in the cURL request. Otherwise,
			// we'd redirect back to the client
			$code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=') + 5, 40);
			echo("SUCCESS! Authorization Code: $code");
		}

		$response->send();
		killme();
	}

}
