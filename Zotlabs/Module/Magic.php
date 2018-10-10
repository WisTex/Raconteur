<?php
namespace Zotlabs\Module;

use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Libzot;


class Magic extends \Zotlabs\Web\Controller {

	function init() {
	
		$ret = array('success' => false, 'url' => '', 'message' => '');
		logger('mod_magic: invoked', LOGGER_DEBUG);
	
		logger('args: ' . print_r($_REQUEST,true),LOGGER_DATA);
	
		$addr = ((x($_REQUEST,'addr')) ? $_REQUEST['addr'] : '');
		$bdest = ((x($_REQUEST,'bdest')) ? $_REQUEST['bdest'] : '');
		$dest = ((x($_REQUEST,'dest')) ? $_REQUEST['dest'] : '');
		$rev  = ((x($_REQUEST,'rev'))  ? intval($_REQUEST['rev'])  : 0);
		$owa  = ((x($_REQUEST,'owa'))  ? intval($_REQUEST['owa'])  : 0);
		$delegate = ((x($_REQUEST,'delegate')) ? $_REQUEST['delegate']  : '');

		if($bdest)
			$dest = hex2bin($bdest);

		$parsed = parse_url($dest);

		if(! $parsed) {
			if($test) {
				$ret['message'] .= 'could not parse ' . $dest . EOL;
				return($ret);
			}
			goaway($dest);
		}
	
		$basepath = $parsed['scheme'] . '://' . $parsed['host'] . (($parsed['port']) ? ':' . $parsed['port'] : ''); 
	
	
		// This is ready-made for a plugin that provides a blacklist or "ask me" before blindly authenticating. 
		// By default, we'll proceed without asking.
	
		$arr = array(
			'channel_id'  => local_channel(),
			'destination' => $dest, 
			'proceed'     => true
		);
	
		call_hooks('magic_auth',$arr);
		$dest = $arr['destination'];
		if(! $arr['proceed']) {
			if($test) {
				$ret['message'] .= 'cancelled by plugin.' . EOL;
				return $ret;
			}
			goaway($dest);
		}
	
		if((get_observer_hash()) && (stripos($dest,z_root()) === 0)) {

			// We are already authenticated on this site and a registered observer.
			// Just redirect.
	
			if($delegate) {

				$r = q("select * from channel left join hubloc on channel_hash = hubloc_hash where hubloc_addr = '%s' limit 1",
					dbesc($delegate)
				);
	
				if($r) {
					$c = array_shift($r);
					if(perm_is_allowed($c['channel_id'],get_observer_hash(),'delegate')) {
						$tmp = $_SESSION;
						$_SESSION['delegate_push']    = $tmp;
						$_SESSION['delegate_channel'] = $c['channel_id'];
						$_SESSION['delegate']         = get_observer_hash();
						$_SESSION['account_id']       = intval($c['channel_account_id']);

						change_channel($c['channel_id']);
					}
				}
			}	
	
			goaway($dest);
		}
	
		if(local_channel()) {
			$channel = \App::get_channel();
	
			// OpenWebAuth

			if($owa) {

				$dest = strip_zids($dest);
				$dest = strip_query_param($dest,'f');

				$data = json_encode([ 'OpenWebAuth' => random_string() ]);
				
				$headers = [];
				$headers['Accept'] = 'application/x-zot+json' ;
				$headers['X-Open-Web-Auth'] = random_string();
				$headers['Digest'] = HTTPSig::generate_digest_header($data);
				$headers['Host'] = $parsed['host'];

				$headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], channel_url($channel),true,'sha512');
				$x = z_post_url($basepath . '/owa',$data,$redirects,[ 'headers' => $headers ]);

				if($x['success']) {
					$j = json_decode($x['body'],true);
					if($j['success'] && $j['encrypted_token']) {
						$token = '';
						openssl_private_decrypt(base64url_decode($j['encrypted_token']),$token,$channel['channel_prvkey']);
						$x = strpbrk($dest,'?&');
						$args = (($x) ? '&owt=' . $token : '?f=&owt=' . $token) . (($delegate) ? '&delegate=1' : '');
						goaway($dest . $args);
					}
				}
			}
		}

		goaway($dest);	
	}
	
}
