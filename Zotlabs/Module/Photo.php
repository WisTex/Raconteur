<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;

require_once('include/security.php');
require_once('include/attach.php');
require_once('include/photo_factory.php');
require_once('include/photos.php');


class Photo extends Controller {

	function init() {
	

		if (ActivityStreams::is_as_request()) {

			$sigdata = HTTPSig::verify(EMPTY_STR);
			if ($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
				if (! check_channelallowed($portable_id)) {
					http_status_exit(403, 'Permission denied');
				}
				if (! check_siteallowed($sigdata['signer'])) {
					http_status_exit(403, 'Permission denied');
				}
				observer_auth($portable_id);
			}
			elseif (Config::get('system','require_authenticated_fetch',false)) {
				http_status_exit(403,'Permission denied');
			}

			$observer_xchan = get_observer_hash();
			$allowed = false;

			$bear = Activity::token_from_request();
			if ($bear) {
				logger('bear: ' . $bear, LOGGER_DEBUG);
			}

			$r = q("select * from item where resource_type = 'photo' and resource_id = '%s' limit 1",
				dbesc(argv(1))
			);
			if ($r) {
				$allowed = attach_can_view($r[0]['uid'],$observer_xchan,argv(1),$bear);
			}
			if (! $allowed) {
				http_status_exit(404,'Permission denied.');
			}
			$channel = channelx_by_n($r[0]['uid']);
		
			$obj = json_decode($r[0]['obj'],true);

			as_return_and_die($obj,$channel);

		}

		$prvcachecontrol = false;
		$streaming = null;
		$channel = null;
		$person = 0;

		switch (argc()) {
			case 4:
				$person = argv(3);
				$res    = argv(2);
				$type   = argv(1);
				break;
			case 2:
				$photo = argv(1);
				break;
			case 1:
			default:
				killme();
		}
	
		$token = ((isset($_REQUEST['token'])) ? $_REQUEST['token'] : EMPTY_STR);
		$observer_xchan = get_observer_hash();

		$default = z_root() . '/' . get_default_profile_photo();
	
		if (isset($type)) {
	
			/**
			 * Profile photos - Access controls on default profile photos are not honoured since they need to be exchanged with remote sites.
			 * 
			 */
	
			if ($type === 'profile') {
				switch ($res) {
					case 'm':
						$resolution = 5;
						$default = z_root() . '/' . get_default_profile_photo(80);
						break;
					case 's':
						$resolution = 6;
						$default = z_root() . '/' . get_default_profile_photo(48);
						break;
					case 'l':
					default:
						$resolution = 4;
						break;
				}
			}
	
			$uid = $person;

			$d = [ 'imgscale' => $resolution, 'channel_id' => $uid, 'default' => $default, 'data'  => '', 'mimetype' => '' ];
			call_hooks('get_profile_photo',$d);

			$resolution = $d['imgscale'];
			$uid        = $d['channel_id']; 	
			$default    = $d['default'];
			$data       = $d['data'];
			$mimetype   = $d['mimetype'];

			if (! $data) {
				$r = q("SELECT * FROM photo WHERE imgscale = %d AND uid = %d AND photo_usage = %d LIMIT 1",
					intval($resolution),
					intval($uid),
					intval(PHOTO_PROFILE)
				);
				if ($r) {
					$data = dbunescbin($r[0]['content']);
					$mimetype = $r[0]['mimetype'];
					if (intval($r[0]['os_storage'])) {
						$data = file_get_contents($data);
					}
				}
			}
			if (! $data) {
				$data = fetch_image_from_url($default,$mimetype);
			}
			if (! $mimetype) {
				$mimetype = 'image/png';
			}
		}
		else {

			$bear = Activity::token_from_request();
			if ($bear) {
				logger('bear: ' . $bear, LOGGER_DEBUG);
			}


			/**
			 * Other photos
			 */
	
			/* Check for a cookie to indicate display pixel density, in order to detect high-resolution
			   displays. This procedure was derived from the "Retina Images" by Jeremey Worboys,
			   used in accordance with the Creative Commons Attribution 3.0 Unported License.
			   Project link: https://github.com/Retina-Images/Retina-Images
			   License link: http://creativecommons.org/licenses/by/3.0/
			*/

			$cookie_value = false;
			if (isset($_COOKIE['devicePixelRatio'])) {
			  $cookie_value = intval($_COOKIE['devicePixelRatio']);
			}
			else {
			  // Force revalidation of cache on next request
			  $cache_directive = 'no-cache';
			  $status = 'no cookie';
			}
	
			$resolution = 0;
	
			if (strpos($photo,'.') !== false) {
				$photo = substr($photo,0,strpos($photo,'.'));
			}
			
			if (substr($photo,-2,1) == '-') {
				$resolution = intval(substr($photo,-1,1));
				$photo = substr($photo,0,-2);
				// If viewing on a high-res screen, attempt to serve a higher resolution image:
				if ($resolution == 2 && ($cookie_value > 1)) {
				    $resolution = 1;
				}
			}
			
			$r = q("SELECT uid, photo_usage FROM photo WHERE resource_id = '%s' AND imgscale = %d LIMIT 1",
				dbesc($photo),
				intval($resolution)
			);
			if ($r) {

				$allowed = (-1);

				if (intval($r[0]['photo_usage'])) {
					$allowed = 1;
					if (intval($r[0]['photo_usage']) === PHOTO_COVER) {
						if ($resolution < PHOTO_RES_COVER_1200) {
							$allowed = (-1);
						}
					}
					if (intval($r[0]['photo_usage']) === PHOTO_PROFILE) {
						if (! in_array($resolution,[4,5,6])) {
							$allowed = (-1);
						}
					}
				}

				if ($allowed === (-1)) {
					$allowed = attach_can_view($r[0]['uid'],$observer_xchan,$photo,$bear);
				}

				$channel = channelx_by_n($r[0]['uid']);

				// Now we'll see if we can access the photo
				$e = q("SELECT * FROM photo WHERE resource_id = '%s' AND imgscale = %d $sql_extra LIMIT 1",
					dbesc($photo),
					intval($resolution)
				);

				$exists = (($e) ? true : false);

				if ($exists && $allowed) {
					$data = dbunescbin($e[0]['content']);
					$mimetype = $e[0]['mimetype'];
					if (intval($e[0]['os_storage'])) {
						$streaming = $data;
					}

					if ($e[0]['allow_cid'] != '' || $e[0]['allow_gid'] != '' || $e[0]['deny_gid'] != '' || $e[0]['deny_gid'] != '')
						$prvcachecontrol = 'no-store, no-cache, must-revalidate';
				}
				else {
					if (! $allowed) {
						http_status_exit(403,'forbidden');
					}
					if (! $exists) {
						http_status_exit(404,'not found');
					}
				}
			}
		}
	
		if (! isset($data)) {
			if (isset($resolution)) {
				switch ($resolution) {
					case 4:
						$data = fetch_image_from_url(z_root() . '/' . get_default_profile_photo(),$mimetype);
						break;
					case 5:
						$data = fetch_image_from_url(z_root() . '/' . get_default_profile_photo(80),$mimetype);
						break;
					case 6:
						$data = fetch_image_from_url(z_root() . '/' . get_default_profile_photo(48),$mimetype);
						break;
					default:
						killme();
				}
			}
		}
	
		if (isset($res) && intval($res) && $res < 500) {
			$ph = photo_factory($data, $mimetype);
			if ($ph->is_valid()) {
				$ph->scaleImageSquare($res);
				$data = $ph->imageString();
				$mimetype = $ph->getType();
			}
		}
	
		if (function_exists('header_remove')) {
			header_remove('Pragma');
			header_remove('pragma');
		}
	
		header("Content-type: " . $mimetype);
	
		if ($prvcachecontrol) {
	
			// it is a private photo that they have permission to view.
			// tell the browser and infrastructure caches not to cache it, 
			// in case permissions change before the next access.
	
			header("Cache-Control: no-store, no-cache, must-revalidate");
	
		}
		else {
			// The cache default for public photos is 1 day to provide a privacy trade-off,
			// as somebody reducing photo permissions on a photo that is already 
			// "in the wild" won't be able to stop the photo from being viewed
			// for this amount amount of time once it is cached.
			// The privacy expectations of your site members and their perception 
			// of privacy where it affects the entire project may be affected.
			// This has performance considerations but we highly recommend you 
			// leave it alone. 
	
			$cache = get_config('system','photo_cache_time');
			if (! $cache) {
				$cache = (3600 * 24); // 1 day
			}
		 	header("Expires: " . gmdate("D, d M Y H:i:s", time() + $cache) . " GMT");
			// Set browser cache age as $cache.  But set timeout of 'shared caches'
			// much lower in the event that infrastructure caching is present.
			$smaxage = intval($cache/12);
			header('Cache-Control: s-maxage='.$smaxage.'; max-age=' . $cache . ';');
	
		}

		// If it's a file resource, stream it. 

		if ($streaming && $channel) {
			if (strpos($streaming,'store') !== false) {
				$istream = fopen($streaming,'rb');
			}
			else {
				$istream = fopen('store/' . $channel['channel_address'] . '/' . $streaming,'rb');
			}
			$ostream = fopen('php://output','wb');
			if ($istream && $ostream) {
				pipe_streams($istream,$ostream);
				fclose($istream);
				fclose($ostream);
			}
		}
		else {
			echo $data;
		}
		killme();
	}
	
}
