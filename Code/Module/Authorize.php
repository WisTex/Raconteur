<?php

namespace Code\Module;

use App;
use DBA;
use Code\Lib\Channel;
use Code\Web\Controller;
use Code\Identity\OAuth2Server;
use Code\Identity\OAuth2Storage;
use OAuth2\Request;
use OAuth2\Response;
use Code\Render\Theme;


class Authorize extends Controller
{

    public function get()
    {
        if (!local_channel()) {
            return login();
        } else {
            $name = $_REQUEST['client_name'];
            if (!$name) {
                $name = (($_REQUEST['client_id']) ?: t('Unknown App'));
            }

            $app = [
                'name' => $name,
                'icon' => (x($_REQUEST, 'logo_uri') ? $_REQUEST['logo_uri'] : z_root() . '/images/icons/plugin.png'),
                'url' => (x($_REQUEST, 'client_uri') ? $_REQUEST['client_uri'] : ''),
            ];

            $link = (($app['url']) ? '<a style="float: none;" href="' . $app['url'] . '">' . $app['name'] . '</a> ' : $app['name']);

            $o .= replace_macros(Theme::get_template('oauth_authorize.tpl'), [
                '$title' => t('Authorize'),
                '$authorize' => sprintf(t('Do you authorize the app %s to access your channel data?'), $link),
                '$app' => $app,
                '$yes' => t('Allow'),
                '$no' => t('Deny'),
                '$client_id' => (x($_REQUEST, 'client_id') ? $_REQUEST['client_id'] : ''),
                '$redirect_uri' => (x($_REQUEST, 'redirect_uri') ? $_REQUEST['redirect_uri'] : ''),
                '$state' => (x($_REQUEST, 'state') ? $_REQUEST['state'] : ''),
            ]);
            return $o;
        }
    }

    public function post()
    {
        if (!local_channel()) {
            return;
        }

        $storage = new OAuth2Storage(DBA::$dba->db);
        $s = new OAuth2Server($storage);

        // TODO: The automatic client registration protocol below should adhere more
        // closely to "OAuth 2.0 Dynamic Client Registration Protocol" defined
        // at https://tools.ietf.org/html/rfc7591

        // If no client_id was provided, generate a new one.
        if (x($_POST, 'client_name')) {
            $client_name = $_POST['client_name'];
        } else {
            $client_name = $_POST['client_name'] = EMPTY_STR;
        }

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

        $request = Request::createFromGlobals();
        $response = new Response();

        // Note, "sub" field must match type and content. $user_id is used to populate - make sure it's a string.
        $channel = Channel::from_id(local_channel());
        $user_id = $channel['channel_id'];

        $client_found = false;
        $client = $storage->getClientDetails($client_id);

        logger('client: ' . print_r($client, true), LOGGER_DATA);

        if ($client) {
            if (intval($client['user_id']) === 0 || intval($client['user_id']) === intval($user_id)) {
                $client_found = true;
                $client_name = $client['client_name'];
                $client_secret = $client['client_secret'];
                // Until "Dynamic Client Registration" is fully tested - allow new clients to assign their own secret in the REQUEST
                if (!$client_secret) {
                    $client_secret = ((isset($_REQUEST['client_secret'])) ? $_REQUEST['client_secret'] : random_string(16));
                }
                $grant_types = $client['grant_types'];
                // Client apps are registered per channel


                logger('client_id: ' . $client_id);
                logger('client_secret: ' . $client_secret);
                logger('redirect_uri: ' . $redirect_uri);
                logger('grant_types: ' . $_REQUEST['grant_types']);
                logger('scope: ' . $_REQUEST['scope']);
                logger('user_id: ' . $user_id);
                logger('client_name: ' . $client_name);

                $storage->setClientDetails($client_id, $client_secret, $redirect_uri, $grant_types, $_REQUEST['scope'], $user_id, $client_name);
            }
        }
        if (!$client_found) {
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
        $s->handleAuthorizeRequest($request, $response, $is_authorized, $user_id);
        if ($is_authorized) {
            $code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=') + 5, 40);
            logger('Authorization Code: ' . $code);
        }

        $response->send();
        killme();
    }
}
