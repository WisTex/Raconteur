<?php

namespace Code\Module;



/**
 * @file Code/Module/Dav.php
 * @brief Initialize Hubzilla's cloud (SabreDAV).
 *
 * Module for accessing the DAV storage area from a DAV client.
 */

use Sabre\DAV as SDAV;
use Sabre\DAV\Auth\Plugin;
use Code\Lib\System;
use Code\Storage\Stdio;
use Code\Lib\Libprofile;
use Code\Lib\Channel;
use Code\Storage\BasicAuth;
use Code\Storage\Browser;
use Code\Web\Controller;
use Code\Web\HTTPSig;

require_once('include/attach.php');
require_once('include/auth.php');
require_once('include/security.php');


class Dav extends Controller
{

    /**
     * @brief Fires up the SabreDAV server.
     *
     */
    public function init()
    {
        if (isset($_SERVER['HTTP_SIGNATURE']) &&
            !isset($_SERVER['REDIRECT_REMOTE_USER']) &&
            !isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Signature ' . $_SERVER['HTTP_SIGNATURE'];
        }

        foreach (['REDIRECT_REMOTE_USER', 'HTTP_AUTHORIZATION'] as $head) {
            /* Basic authentication */

            if (array_key_exists($head, $_SERVER) && str_starts_with(trim($_SERVER[$head]), 'Basic')) {
                $userpass = @base64_decode(substr(trim($_SERVER[$head]), 6));
                if (strlen($userpass)) {
                    list($name, $password) = explode(':', $userpass);
                    $_SERVER['PHP_AUTH_USER'] = $name;
                    $_SERVER['PHP_AUTH_PW'] = $password;
                }
                break;
            }

            /* Signature authentication */

            if (array_key_exists($head, $_SERVER) && str_starts_with(trim($_SERVER[$head]), 'Signature')) {
                if ($head !== 'HTTP_AUTHORIZATION') {
                    $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER[$head];
                    continue;
                }

                $sigblock = HTTPSig::parse_sigheader($_SERVER[$head]);
                if ($sigblock) {
                    $keyId = str_replace('acct:', '', $sigblock['keyId']);
                    if ($keyId) {
                        $r = hubloc_id_addr_query($keyId);
                        if ($r) {
                            foreach ($r as $rv) {
                                $c = Channel::from_hash($rv['hubloc_hash']);
                                if ($c) {
                                    $a = q(
                                        "select * from account where account_id = %d limit 1",
                                        intval($c['channel_account_id'])
                                    );
                                    if ($a) {
                                        $record = ['channel' => $c, 'account' => $a[0]];
                                        $channel_login = $c['channel_id'];
                                    }
                                }
                            }
                            if (!$record) {
                                continue;
                            }

                            if ($record) {
                                $verified = HTTPSig::verify('', $record['channel']['channel_pubkey']);
                                if (!($verified && $verified['header_signed'] && $verified['header_valid'])) {
                                    $record = null;
                                }
                                if ($record['account']) {
                                    authenticate_success($record['account']);
                                    if ($channel_login) {
                                        change_channel($channel_login);
                                    }
                                }
                                if ($verified && $verified['header_signed'] && $verified['header_valid'] && $record) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            if ($verified && $verified['header_signed'] && $verified['header_valid'] && $record['account']) {
                break;
            }
        }

        if (!is_dir('store')) {
            Stdio::mkdir('store', STORAGE_DEFAULT_PERMISSIONS, false);
        }

        if (argc() > 1) {
            Libprofile::load(argv(1), 0);
        }


        $auth = new BasicAuth();
//      $auth->observer = get_observer_hash();

        $auth->setRealm(ucfirst(System::get_platform_name()) . ' ' . 'WebDAV');

        $rootDirectory = new \Code\Storage\Directory('/', $auth);

        // A SabreDAV server-object
        $server = new SDAV\Server($rootDirectory);


        $authPlugin = new Plugin($auth);
        $server->addPlugin($authPlugin);


        // prevent overwriting changes each other with a lock backend
        $lockBackend = new SDAV\Locks\Backend\File('cache/locks');
        $lockPlugin = new SDAV\Locks\Plugin($lockBackend);

        $server->addPlugin($lockPlugin);

        // provide a directory view for the cloud in Hubzilla
        $browser = new Browser($auth);
        $auth->setBrowserPlugin($browser);

        // Experimental QuotaPlugin
        // $server->addPlugin(new \Code\Storage\QuotaPlugin($auth));

        // All we need to do now, is to fire up the server
        $server->exec();

        killme();
    }
}
