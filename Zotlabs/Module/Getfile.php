<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Web\HTTPSig;

/**
 * module: getfile
 * 
 * used for synchronising files and photos across clones
 * 
 * The site initiating the file operation will send a sync packet to known clones.
 * They will respond by building the DB structures they require, then will provide a
 * post request to this site to grab the file data. This is sent as a stream direct to
 * disk at the other end, avoiding memory issues.
 *
 * Since magic-auth cannot easily be used by the CURL process at the other end,
 * we will require a signed request which includes a timestamp. This should not be 
 * used without SSL and is potentially vulnerable to replay if an attacker decrypts 
 * the SSL traffic fast enough. The amount of time slop is configurable but defaults
 * to 3 minutes.
 * 
 */



require_once('include/attach.php');


class Getfile extends Controller
{

    public function post()
    {

        $header_verified = false;

        logger('getfile_args: ' . print_r($_POST, true));

        $hash = $_POST['hash'];
        $resource = $_POST['resource'];
        $revision = intval($_POST['revision']);
        $resolution = ((isset($_POST['resolution'])) ? intval($_POST['resolution']) : (-1));

        if (argc() > 1) {
            $verify_hash = argv(1);
            if ($verify_hash !== $resource) {
                logger('resource mismatch');
                killme();
            }
        }

        if (!$hash) {
            logger('no sender hash');
            killme();
        }

        foreach (['REDIRECT_REMOTE_USER', 'HTTP_AUTHORIZATION'] as $head) {
            if (array_key_exists($head, $_SERVER) && substr(trim($_SERVER[$head]), 0, 9) === 'Signature') {
                if ($head !== 'HTTP_AUTHORIZATION') {
                    $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER[$head];
                    continue;
                }

                $verified = HTTPSig::verify('');
                if ($verified && $verified['header_signed'] && $verified['header_valid']) {
                    $r = q("select hubloc_hash from hubloc where hubloc_id_url = '%s' or hubloc_addr = '%s' limit 1",
                        dbesc($verified['signer']),
                        dbesc(str_replace('acct:', '', $verified['signer']))
                    );
                    if ($r && $r[0]['hubloc_hash'] === $hash) {
                        $header_verified = true;
                    }
                }
            }
        }

        if (!$header_verified) {
            http_status_exit(403, 'Permission denied');
        }

        $channel = channelx_by_hash($hash);

        if (!$channel) {
            logger('error: missing info');
            killme();
        }

        if ($resolution > 0) {
            $r = q("select * from photo where resource_id = '%s' and uid = %d and imgscale = %d limit 1",
                dbesc($resource),
                intval($channel['channel_id']),
                intval($resolution)
            );
            if ($r) {
                header('Content-type: ' . $r[0]['mimetype']);

                if (intval($r[0]['os_storage'])) {
                    $fname = dbunescbin($r[0]['content']);
                    if (strpos($fname, 'store') !== false)
                        $istream = fopen($fname, 'rb');
                    else
                        $istream = fopen('store/' . $channel['channel_address'] . '/' . $fname, 'rb');
                    $ostream = fopen('php://output', 'wb');
                    if ($istream && $ostream) {
                        pipe_streams($istream, $ostream);
                        fclose($istream);
                        fclose($ostream);
                    }
                } else {
                    echo dbunescbin($r[0]['content']);
                }
            }
            killme();
        }

        $r = attach_by_hash($resource, $channel['channel_hash'], $revision);

        if (!$r['success']) {
            logger('attach_by_hash failed: ' . $r['message']);
            notice($r['message'] . EOL);
            return;
        }

        header('Content-type: ' . $r['data']['filetype']);
        header('Content-Disposition: attachment; filename="' . $r['data']['filename'] . '"');
        if (intval($r['data']['os_storage'])) {
            $fname = dbunescbin($r['data']['content']);
            if (strpos($fname, 'store') !== false)
                $istream = fopen($fname, 'rb');
            else
                $istream = fopen('store/' . $channel['channel_address'] . '/' . $fname, 'rb');
            $ostream = fopen('php://output', 'wb');
            if ($istream && $ostream) {
                pipe_streams($istream, $ostream);
                fclose($istream);
                fclose($ostream);
            }
        } else {
            echo dbunescbin($r['data']['content']);
        }
        killme();
    }
}
