<?php
/**
 * @file include/photos.php
 * @brief Functions related to photo handling.
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Activity;
use Zotlabs\Access\AccessControl;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Web\HTTPHeaders;
use Zotlabs\Daemon\Run;

require_once('include/permissions.php');
require_once('include/photo_factory.php');
require_once('include/attach.php');

/**
 * @brief Upload a photo.
 *
 * @param array $channel
 * @param array $observer
 * @param array $args
 * @return array
 */

function photo_upload($channel, $observer, $args) {

	$ret = [ 'success' => false ];
	$channel_id = $channel['channel_id'];
	$account_id = $channel['channel_account_id'];
	
	if (! perm_is_allowed($channel_id, $observer['xchan_hash'], 'write_storage')) {
		$ret['message'] = t('Permission denied.');
		return $ret;
	}

	/*
	 * Determine the album to use
	 */

	$album    = $args['album'];

	$visible = ((intval($args['visible']) || $args['visible'] === 'true') ? 1 : 0);
	$deliver = ((array_key_exists('deliver', $args)) ? intval($args['deliver']) : 1 ); 


	// Set to default channel permissions. If the parent directory (album) has permissions set,
	// use those instead. If we have specific permissions supplied, they take precedence over
	// all other settings. 'allow_cid' being passed from an external source takes priority over channel settings.
	// ...messy... needs re-factoring once the photos/files integration stabilises

	$acl = new AccessControl($channel);
	if (array_key_exists('directory',$args) && $args['directory']) {
		$acl->set($args['directory']);
	}
	if (array_key_exists('allow_cid',$args)) {
		$acl->set($args);
	}
	if ((array_key_exists('group_allow',$args))
		|| (array_key_exists('contact_allow',$args))
		|| (array_key_exists('group_deny',$args))
		|| (array_key_exists('contact_deny',$args))) {
		$acl->set_from_array($args);
	}

	$ac = $acl->get();

	$width = $height = 0;

	if($args['getimagesize']) {
		$width  = $args['getimagesize'][0];
		$height = $args['getimagesize'][1];
	}

	$os_storage = 0;

	$max_thumb = get_config('system','max_thumbnail',1600);

	if($args['os_syspath'] && $args['getimagesize']) {
		if($args['getimagesize'][0] > $max_thumb || $args['getimagesize'][1] > $max_thumb) {
			$imagick_path = get_config('system','imagick_convert_path');
			if($imagick_path && @file_exists($imagick_path)) {
				$tmp_name = $args['os_syspath'] . '-001';
				$newsize = photo_calculate_scale(array_merge($args['getimagesize'],['max' => $max_thumb]));
				$cmd = $imagick_path . ' ' . escapeshellarg(PROJECT_BASE . '/' . $args['os_syspath']) . ' -resize ' . $newsize . ' ' . escapeshellarg(PROJECT_BASE . '/' . $tmp_name);
				logger('imagick thumbnail command: ' . $cmd);
				for($x = 0; $x < 4; $x ++) {
					exec($cmd);
					if(file_exists($tmp_name)) {
						break;
					}
					logger('imagick scale failed. Retrying.');
					continue;
				}
				if(! file_exists($tmp_name)) {
					logger('imagick scale failed. Abort.');
					return $ret;
				}

				$imagedata = @file_get_contents($tmp_name);
				$filesize = @filesize($args['os_syspath']);
			}
			else {
				$imagedata = @file_get_contents($args['os_syspath']);
				$filesize = strlen($imagedata);
			}
		}
		else {
			$imagedata = @file_get_contents($args['os_syspath']);
			$filesize = strlen($imagedata);
		}
		$filename = $args['filename'];
		// this is going to be deleted if it exists
		$src = '/tmp/deletemenow';
		$type = $args['getimagesize']['mime'];
		$os_storage = 1;
	}
	elseif ($args['data'] || $args['content']) {

		// allow an import from a binary string representing the image.
		// This bypasses the upload step and max size limit checking

		$imagedata = (($args['content']) ? $args['content'] : $args['data']);
		$filename = $args['filename'];
		$filesize = strlen($imagedata);
		// this is going to be deleted if it exists
		$src = '/tmp/deletemenow';
		$type = (($args['mimetype']) ? $args['mimetype'] : $args['type']);
	} else {
		$f = array('src' => '', 'filename' => '', 'filesize' => 0, 'type' => '');

		if (x($f,'src') && x($f,'filesize')) {
			$src      = $f['src'];
			$filename = $f['filename'];
			$filesize = $f['filesize'];
			$type     = $f['type'];
		} else {
			$src      = $_FILES['userfile']['tmp_name'];
			$filename = basename($_FILES['userfile']['name']);
			$filesize = intval($_FILES['userfile']['size']);
			$type     = $_FILES['userfile']['type'];
		}

		if (! $type)
			$type=guess_image_type($filename);

		logger('Received file: ' . $filename . ' as ' . $src . ' ('. $type . ') ' . $filesize . ' bytes', LOGGER_DEBUG);

		$maximagesize = get_config('system','maximagesize');

		if (($maximagesize) && ($filesize > $maximagesize)) {
			$ret['message'] =  sprintf ( t('Image exceeds website size limit of %lu bytes'), $maximagesize);
			@unlink($src);
			/**
			 * @hooks photo_upload_end
			 *   Called when a photo upload has been processed.
			 */
			call_hooks('photo_upload_end', $ret);
			return $ret;
		}

		if (! $filesize) {
			$ret['message'] = t('Image file is empty.');
			@unlink($src);
			/**
			 * @hooks photo_post_end
			 *   Called after uploading a photo.
			 */
			call_hooks('photo_post_end', $ret);
			return $ret;
		}

		logger('Loading the contents of ' . $src , LOGGER_DEBUG);
		$imagedata = @file_get_contents($src);
	}

	$r = q("select sum(filesize) as total from photo where aid = %d and imgscale = 0 ",
		intval($account_id)
	);

	$limit = engr_units_to_bytes(service_class_fetch($channel_id,'photo_upload_limit'));

	if (($r) && ($limit !== false) && (($r[0]['total'] + strlen($imagedata)) > $limit)) {
		$ret['message'] = upgrade_message();
		@unlink($src);
		/**
		 * @hooks photo_post_end
		 *   Called after uploading a photo.
		 */
		call_hooks('photo_post_end', $ret);
		return $ret;
	}

	$ph = photo_factory($imagedata, $type);

	if (! $ph->is_valid()) {
		$ret['message'] = t('Unable to process image');
		logger('unable to process image');
		@unlink($src);
		/**
		 * @hooks photo_upload_end
		 *   Called when a photo upload has been processed.
		 */
		call_hooks('photo_upload_end', $ret);
		return $ret;
	}

	// obtain exif data from the source file if present

	$exif = $ph->exif(($args['os_syspath']) ? $args['os_syspath'] : $src);

	if($exif) {
		$ph->orient($exif);
	}

	$ph->clearexif();

	if(get_pconfig($channel_id,'system','clearexif',false)) {
	
		// only do this on the original if it was uploaded by some method other than WebDAV.
		// Otherwise Microsoft Windows will note the file size mismatch, erase the file and
		// upload it again and again. 

	}

	@unlink($src);

	$max_length = get_config('system','max_image_length', MAX_IMAGE_LENGTH);
	if ($max_length > 0) {
		$ph->scaleImage($max_length);
	}
	
	if (! $width) {
		$width  = $ph->getWidth();
	}
	if (! $height) {
		$height = $ph->getHeight();
	}
	$smallest = 0;

	$photo_hash = (($args['resource_id']) ? $args['resource_id'] : photo_new_resource());

	$visitor = '';
	if ($channel['channel_hash'] !== $observer['xchan_hash'])
		$visitor = $observer['xchan_hash'];

	$errors = false;

	$p = array('aid' => $account_id, 'uid' => $channel_id, 'xchan' => $visitor, 'resource_id' => $photo_hash,
		'filename' => $filename, 'album' => $album, 'imgscale' => 0, 'photo_usage' => PHOTO_NORMAL,
		'width' => $width, 'height' => $height,
		'allow_cid' => $ac['allow_cid'], 'allow_gid' => $ac['allow_gid'],
		'deny_cid' => $ac['deny_cid'], 'deny_gid' => $ac['deny_gid'],
		'os_storage' => $os_storage, 'os_syspath' => $args['os_syspath'],
		'os_path' => $args['os_path'], 'display_path' => $args['display_path']
	);
	if($args['created'])
		$p['created'] = $args['created'];
	if($args['edited'])
		$p['edited'] = $args['edited'];
	if($args['title'])
		$p['title'] = $args['title'];

	if ($args['description']) {
		$p['description'] = $args['description'];
	}

	$alt_desc = ((isset($p['description']) && $p['description']) ? $p['description'] : $p['filename']);

	$url = [];

	$r0 = $ph->save($p);
	$url[0] = [
		'type' => 'Link',
		'mediaType' => $type,
		'summary' => $alt_desc,
		'href' => z_root() . '/photo/' . $photo_hash . '-0.' . $ph->getExt(),
		'width' => $width,
		'height' => $height
	];
	if(! $r0)
		$errors = true;

	unset($p['os_storage']);
	unset($p['os_syspath']);
	unset($p['width']);
	unset($p['height']);

	if(($width > 1024 || $height > 1024) && (! $errors))
		$ph->scaleImage(1024);

	$p['imgscale'] = 1;
	$r1 = $ph->storeThumbnail($p, PHOTO_RES_1024);
	$url[1] = [
		'type' => 'Link',
		'mediaType' => $type,
		'summary' => $alt_desc,
		'href' => z_root() . '/photo/' . $photo_hash . '-1.' . $ph->getExt(),
		'width' => $ph->getWidth(),
		'height' => $ph->getHeight()
	];
	if(! $r1)
		$errors = true;

	if(($width > 640 || $height > 640) && (! $errors))
		$ph->scaleImage(640);

	$p['imgscale'] = 2;
	$r2 = $ph->storeThumbnail($p, PHOTO_RES_640);
	$url[2] = [
		'type' => 'Link',
		'mediaType' => $type,
		'summary' => $alt_desc,
		'href' => z_root() . '/photo/' . $photo_hash . '-2.' . $ph->getExt(),
		'width' => $ph->getWidth(),
		'height' => $ph->getHeight()
	];
	if(! $r2)
		$errors = true;

	if(($width > 320 || $height > 320) && (! $errors))
		$ph->scaleImage(320);

	$p['imgscale'] = 3;
	$r3 = $ph->storeThumbnail($p, PHOTO_RES_320);
	$url[3] = [
		'type' => 'Link',
		'mediaType' => $type,
		'summary' => $alt_desc,
		'href' => z_root() . '/photo/' . $photo_hash . '-3.' . $ph->getExt(),
		'width' => $ph->getWidth(),
		'height' => $ph->getHeight()
	];
	if(! $r3)
		$errors = true;

	if($errors) {
		q("delete from photo where resource_id = '%s' and uid = %d",
			dbesc($photo_hash),
			intval($channel_id)
		);
		$ret['message'] = t('Photo storage failed.');
		logger('Photo store failed.');
		/**
		 * @hooks photo_upload_end
		 *   Called when a photo upload has been processed.
		 */
		call_hooks('photo_upload_end', $ret);
		return $ret;
	}

	$url[] = [ 
		'type'      => 'Link',
		'mediaType' => 'text/html',
		'href'      => z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $photo_hash
	];

	$item_hidden = (($visible) ? 0 : 1 );

	$lat = $lon = null;

	if($exif && Apps::system_app_installed($channel_id,'Photomap')) {
		$gps = null;
		if(array_key_exists('GPS',$exif)) {
			$gps = $exif['GPS'];
		}
		elseif(array_key_exists('GPSLatitude',$exif)) {
			$gps = $exif;
		}
		if($gps) {
			$lat = getGps($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
			$lon = getGps($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
		}
	}

	$title = ((isset($args['title']) && $args['title']) ? $args['title'] : $args['filename']);

	$desc = htmlspecialchars($alt_desc);
	
	$found_tags = linkify_tags($args['body'], $channel_id);
	
	$alt = ' alt="' . $desc . '"' ;

	$scale = 1;
	$width = $url[1]['width'];
	$height = $url[1]['height'];
	$tag = (($r1) ? '[zmg width="' . $width . '" height="' . $height . '"' . $alt . ']' : '[zmg' . $alt . ']');

	$author_link = '[zrl=' . z_root() . '/channel/' . $channel['channel_address'] . ']' . $channel['channel_name'] . '[/zrl]';

	$photo_link = '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $photo_hash . ']' . t('a new photo') . '[/zrl]';

	$album_link = '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/album/' . $args['directory']['hash'] . ']' . ((strlen($album)) ? $album : '/') . '[/zrl]';

	$activity_format = sprintf(t('%1$s posted %2$s to %3$s','photo_upload'), $author_link, $photo_link, $album_link);

	$body = (($args['body']) ? $args['body'] : '') . '[footer]' . $activity_format . '[/footer]';

	// If uploaded into a post, this is the text that is returned to the webapp for inclusion in the post.

	$obj_body =  '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $photo_hash . ']'
		. $tag . z_root() . "/photo/{$photo_hash}-{$scale}." . $ph->getExt() . '[/zmg]'
		. '[/zrl]';

	// Create item object
	$object = [
		'type'      => ACTIVITY_OBJ_PHOTO,
		'name'      => $title,
		'summary'   => $p['description'],
		'published' => datetime_convert('UTC','UTC',$p['created'],ATOM_TIME),
		'updated'   => datetime_convert('UTC','UTC',$p['edited'],ATOM_TIME),
		// This is a placeholder and will get over-ridden by the item mid, which is critical for sharing as a conversational item over activitypub
		'id'        => z_root() . '/photo/' . $photo_hash,
		'url'       => $url,
		'source'    => [ 'content' => $body, 'mediaType' => 'text/bbcode' ],
		'content'   => bbcode($body)
	];

	$public = (($ac['allow_cid'] || $ac['allow_gid'] || $ac['deny_cid'] || $ac['deny_gid']) ? false : true);

	if ($public) {
		$object['to'] = [ ACTIVITY_PUBLIC_INBOX ];
		$object['cc'] = [ z_root() . '/followers/' . $channel['channel_address'] ];
	}
	else {
		$object['to'] = Activity::map_acl(array_merge($ac, ['item_private' => 1 - intval($public) ]));
	}

	$target = [
		'type'    => 'orderedCollection',
		'name'    => ((strlen($album)) ? $album : '/'),  
		'id'      => z_root() . '/album/' . $channel['channel_address'] . ((isset($args['folder'])) ? '/' . $args['folder'] : EMPTY_STR)
	];

	$post_tags = [];

	if ($found_tags) {
		foreach($found_tags as $result) {
			$success = $result['success'];
			if($success['replaced']) {
				$post_tags[] = array(
					'uid'   => $channel['channel_id'],
					'ttype' => $success['termtype'],
					'otype' => TERM_OBJ_POST,
					'term'  => $success['term'],
					'url'   => $success['url']
				);
			}
		}
	}

	// Create item container
	if ($args['item']) {
		foreach ($args['item'] as $i) {

			$item = get_item_elements($i);
			$force = false;

			if($item['mid'] === $item['parent_mid']) {

				$object['id'] = $item['mid'];
				$item['summary'] = $summary;
				$item['body'] = $body; 
				$item['mimetype'] = 'text/bbcode';
				$item['obj_type'] = ACTIVITY_OBJ_PHOTO;
				$item['obj']	= json_encode($object);

				$item['tgt_type'] = 'orderedCollection';
				$item['target']	= json_encode($target);
				if ($post_tags) {
					$arr['term'] = $post_tags;
				}
				$force = true;
			}
			$r = q("select id, edited from item where mid = '%s' and uid = %d limit 1",
				dbesc($item['mid']),
				intval($channel['channel_id'])
			);
			if ($r) {
				if (($item['edited'] > $r[0]['edited']) || $force) {
					$item['id'] = $r[0]['id'];
					$item['uid'] = $channel['channel_id'];
					item_store_update($item,false,$deliver);
					continue;
				}
			}
			else {
				$item['aid'] = $channel['channel_account_id'];
				$item['uid'] = $channel['channel_id'];
				$item_result = item_store($item,false,$deliver);
			}
		}
	}
	else {
		
		$uuid = new_uuid();
		$mid = z_root() . '/item/' . $uuid;

		$object['id'] = $mid;

		$arr = [
			'aid'             => $account_id,
			'uid'             => $channel_id,
			'uuid'            => $uuid,
			'mid'             => $mid,
			'parent_mid'      => $mid,
			'created'         => $p['created'],
			'edited'          => $p['edited'],
			'item_hidden'     => $item_hidden,
			'resource_type'   => 'photo',
			'resource_id'     => $photo_hash,
			'owner_xchan'     => $channel['channel_hash'],
			'author_xchan'    => $observer['xchan_hash'],
			'title'           => $title,
			'allow_cid'       => $ac['allow_cid'],
			'allow_gid'       => $ac['allow_gid'],
			'deny_cid'        => $ac['deny_cid'],
			'deny_gid'        => $ac['deny_gid'],
			'verb'            => ACTIVITY_POST,
			'obj_type'        => ACTIVITY_OBJ_PHOTO,
			'obj'             => json_encode($object),
			'tgt_type'        => 'orderedCollection',
			'target'	      => json_encode($target),
			'item_wall'       => 1,
			'item_origin'     => 1,
			'item_thread_top' => 1,
			'item_private'    => intval($acl->is_private()),
			'summary'         => $summary,
			'body'            => $body
		];

		if ($post_tags) {
			$arr['term'] = $post_tags;
		}

		$arr['plink']           = z_root() . '/channel/' . $channel['channel_address'] . '/?f=&mid=' . urlencode($arr['mid']);

		if($lat && $lon)
			$arr['coord'] = $lat . ' ' . $lon;

		// this one is tricky because the item and the photo have the same permissions, those of the photo.
		// Use the channel read_stream permissions to get the correct public_policy for the item and recalculate the
		// private flag accordingly. This may cause subtle bugs due to custom permissions roles. We want to use
		// public policy when federating items to other sites, but should probably ignore them when accessing the item
		// in the photos pages - using the photos permissions instead. We need the public policy to keep the photo
		// linked item from leaking into the feed when somebody has a channel with read_stream restrictions.

		$arr['public_policy']   = map_scope(PermissionLimits::Get($channel['channel_id'],'view_stream'),true);
		if($arr['public_policy'])
			$arr['item_private'] = 1;


		$result = item_store($arr,false,$deliver);
		$item_id = $result['item_id'];

		if($visible && $deliver)
			Run::Summon( [ 'Notifier', 'wall-new', $item_id ] );
	}

	$ret['success'] = true;
	$ret['item'] = $arr;
	$ret['body'] = $obj_body;
	$ret['resource_id'] = $photo_hash;
	$ret['photoitem_id'] = $item_id;

	/**
	 * @hooks photo_upload_end
	 *   Called when a photo upload has been processed.
	 */
	call_hooks('photo_upload_end', $ret);

	return $ret;
}


function photo_calculate_scale($arr) {

	$max    = $arr['max'];
	$width  = $arr[0];
	$height = $arr[1];

	$dest_width = $dest_height = 0;

	if (! ($width && $height)) {
		return false;
	}

	if ($width > $max && $height > $max) {

		// very tall image (greater than 16:9)
		// constrain the width - let the height float.

		if ((($height * 9) / 16) > $width) {
			$dest_width = $max;
 			$dest_height = intval(( $height * $max ) / $width);
		}

		// else constrain both dimensions

		elseif ($width > $height) {
			$dest_width = $max;
			$dest_height = intval(( $height * $max ) / $width);
		}
		else {
			$dest_width = intval(( $width * $max ) / $height);
			$dest_height = $max;
		}
	}
	else {
		if ( $width > $max ) {
			$dest_width = $max;
			$dest_height = intval(( $height * $max ) / $width);
		}
		else {
			if ( $height > $max ) {

				// very tall image (greater than 16:9)
				// but width is OK - don't do anything

				if ((($height * 9) / 16) > $width) {
					$dest_width = $width;
 					$dest_height = $height;
				}
				else {
					$dest_width = intval(( $width * $max ) / $height);
					$dest_height = $max;
				}
			}
			else {
				$dest_width = $width;
				$dest_height = $height;
			}
		}
	}

	return $dest_width . 'x' . $dest_height;
}

/**
 * @brief Returns a list with all photo albums observer is allowed to see.
 *
 * Returns an associative array with all albums where observer has permissions.
 *
 * @param array $channel
 * @param array $observer
 * @param array $sort_key (optional) default album
 * @param array $direction (optional) default asc
 *
 * @return bool|array false if no view_storage permission or an array
 *   * \e boolean \b success
 *   * \e array \b albums
 */
function photos_albums_list($channel, $observer, $sort_key = 'display_path', $direction = 'asc') {

	$channel_id     = $channel['channel_id'];
	$observer_xchan = (($observer) ? $observer['xchan_hash'] : '');

	if (! perm_is_allowed($channel_id, $observer_xchan, 'view_storage')) {
		return false;
	}

	$sql_extra = permissions_sql($channel_id,$observer_xchan);

	$sort_key = dbesc($sort_key);
	$direction = dbesc($direction);

	$r = q("select display_path, hash from attach where is_dir = 1 and uid = %d $sql_extra order by $sort_key $direction",
		intval($channel_id)
	);

	// add a 'root directory' to the results
	
	array_unshift($r,[ 'display_path' => '/', 'hash' => '' ]);
	$str = ids_to_querystr($r,'hash',true);

	$albums = [];

	if ($str) {
		$x = q("select count( distinct hash ) as total, folder from attach where is_photo = 1 and uid = %d and folder in ( $str ) $sql_extra group by folder ",
			intval($channel_id)
		);
		if ($x) {
			foreach ($r as $rv) {
				foreach ($x as $xv) {
					if ($xv['folder'] === $rv['hash']) {
						if ($xv['total'] != 0 && attach_can_view_folder($channel_id,$observer_xchan,$xv['folder'])) {
							$albums[] = [ 'album' => $rv['display_path'], 'folder' => $xv['folder'], 'total' => $xv['total'] ];
						}
						continue;
					}
				}
			}
		}
	}

	// add various encodings to the array so we can just loop through and pick them out in a template

	$ret = [ 'success' => false ];

	if ($albums) {
		$ret['success'] = true;
		$ret['albums'] = [];
		foreach ($albums as $k => $album) {
			$entry = [
				'text'      => (($album['album']) ? $album['album'] : '/'),
				'shorttext' => (($album['album']) ? ellipsify($album['album'],28) : '/'),
				'jstext'    => (($album['album']) ? addslashes($album['album']) : '/'),
				'total'     => $album['total'],
				'url'       => z_root() . '/photos/' . $channel['channel_address'] . '/album/' . $album['folder'],
				'urlencode' => urlencode($album['album']),
				'bin2hex'   => $album['folder']
			];
			$ret['albums'][] = $entry;
		}
	}

	App::$data['albums'] = $ret;

	return $ret;
}

function photos_album_widget($channelx,$observer,$sortkey = 'display_path',$direction = 'asc') {

	$o = EMPTY_STR;

	if (array_key_exists('albums', App::$data)) {
		$albums = App::$data['albums'];
	}
	else {
		$albums = photos_albums_list($channelx,$observer,$sortkey,$direction);
	}
	
	if ($albums['success']) {
		$o = replace_macros(get_markup_template('photo_albums.tpl'), [
			'$nick'    => $channelx['channel_address'],
			'$title'   => t('Photo Albums'),
			'$recent'  => t('Recent Photos'),
			'$albums'  => $albums['albums'],
			'$baseurl' => z_root(),
			'$upload'  => ((perm_is_allowed($channelx['channel_id'],(($observer) ? $observer['xchan_hash'] : ''),'write_storage'))
				? t('Upload New Photos') : '')
		]);
	}

	return $o;
}

/**
 * @brief Return an array of photos.
 *
 * @param array $channel
 * @param array $observer
 * @param string $album (optional) default empty
 * @return boolean|array
 */
function photos_list_photos($channel, $observer, $album = '') {

	$channel_id     = $channel['channel_id'];
	$observer_xchan = (($observer) ? $observer['xchan_hash'] : '');

	if (! perm_is_allowed($channel_id,$observer_xchan,'view_storage')) {
		return false;
	}

	$sql_extra = permissions_sql($channel_id);

	if ($album) {
		$sql_extra .= " and album = '" . protect_sprintf(dbesc($album)) . "' ";
	}
	
	$ret = [ 'success' => false ];

	$r = q("select resource_id, created, edited, title, description, album, filename, mimetype, height, width, filesize, imgscale, photo_usage, allow_cid, allow_gid, deny_cid, deny_gid from photo where uid = %d and photo_usage in ( %d, %d ) $sql_extra ",
		intval($channel_id),
		intval(PHOTO_NORMAL),
		intval(PHOTO_PROFILE)
	);

	if ($r) {
		for ($x = 0; $x < count($r); $x ++) {
			$r[$x]['src'] = z_root() . '/photo/' . $r[$x]['resource_id'] . '-' . $r[$x]['imgscale'];
		}
		$ret['success'] = true;
		$ret['photos'] = $r;
	}

	return $ret;
}

/**
 * @brief Check if given photo album exists in channel.
 *
 * @param int $channel_id id of the channel
 * @param string $observer_hash
 * @param string $album name of the album
 * @return boolean
 */
function photos_album_exists($channel_id, $observer_hash, $album) {

	$sql_extra = permissions_sql($channel_id, $observer_hash);

	$r = q("SELECT folder, hash, is_dir, filename, os_path, display_path FROM attach WHERE hash = '%s' AND is_dir = 1 AND uid = %d $sql_extra limit 1",
		dbesc($album),
		intval($channel_id)
	);

	return (($r) ? array_shift($r) : false);
}

/**
 * @brief Renames a photo album in a channel.
 *
 * @todo Do we need to check if new album name already exists?
 *
 * @param int $channel_id id of the channel
 * @param string $oldname The name of the album to rename
 * @param string $newname The new name of the album
 * @return bool|array
 */
function photos_album_rename($channel_id, $oldname, $newname) {
	return q("UPDATE photo SET album = '%s' WHERE album = '%s' AND uid = %d",
		dbesc($newname),
		dbesc($oldname),
		intval($channel_id)
	);
}

/**
 * @brief returns the DB escaped comma separated list of the contents (by hash name) of a given photo album
 * based on the creator. This is used to ensure guests can only edit content they created. The page owner and site
 * admin can edit any content owned by this channel.
 *
 * @param int $channel_id
 * @param string $album
 * @param string $remote_xchan (optional) default empty
 * @return string|boolean
 */
function photos_album_get_db_idstr($channel_id, $album, $remote_xchan = '') {

	if($remote_xchan) {
		$r = q("SELECT hash from attach where creator = '%s' and uid = %d and folder = '%s' ",
			dbesc($remote_xchan),
			intval($channel_id),
			dbesc($album)
		);
	}
	else {
		$r = q("SELECT hash from attach where uid = %d and folder = '%s' ",
			intval($channel_id),
			dbesc($album)
		);
	}
	if ($r) {
		return ids_to_querystr($r,'hash',true);
	}

	return false;
}

function photos_album_get_db_idstr_admin($channel_id, $album) {

	if(! is_site_admin())
		return false;

	$r = q("SELECT hash from attach where uid = %d and folder = '%s' ",
		intval($channel_id),
		dbesc($album)
	);

	if ($r) {
		return ids_to_querystr($r,'hash',true);
	}

	return false;
}



/**
 * @brief Creates a new photo item.
 *
 * @param array $channel
 * @param string $creator_hash
 * @param array $photo
 * @param boolean $visible (optional) default false
 * @return int item_id
 */
function photos_create_item($channel, $creator_hash, $photo, $visible = false) {

	// Create item container

	$item_hidden = (($visible) ? 0 : 1 );

	$uuid = new_uuid();
	$mid = z_root() . '/item/' . $uuid;

	$arr = [];

	$arr['aid']             = $channel['channel_account_id'];
	$arr['uid']             = $channel['channel_id'];
	$arr['uuid']            = $uuid;
	$arr['mid']             = $mid;
	$arr['parent_mid']      = $mid;
	$arr['item_wall']       = 1;
	$arr['item_origin']     = 1;
	$arr['item_thread_top'] = 1;
	$arr['item_hidden']     = $item_hidden;
	$arr['resource_type']   = 'photo';
	$arr['resource_id']     = $photo['resource_id'];
	$arr['owner_xchan']     = $channel['channel_hash'];
	$arr['author_xchan']    = $creator_hash;

	$arr['allow_cid']       = $photo['allow_cid'];
	$arr['allow_gid']       = $photo['allow_gid'];
	$arr['deny_cid']        = $photo['deny_cid'];
	$arr['deny_gid']        = $photo['deny_gid'];

	$arr['plink']           = z_root() . '/channel/' . $channel['channel_address'] . '/?f=&mid=' . urlencode($arr['mid']);

	$arr['body']            = '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $photo['resource_id'] . ']'
		. '[zmg]' . z_root() . '/photo/' . $photo['resource_id'] . '-' . $photo['imgscale'] . '[/zmg]'
		. '[/zrl]';

	$result = item_store($arr);
	$item_id = $result['item_id'];

	return $item_id;
}


function getGps($exifCoord, $hemi) {

	$degrees = count($exifCoord) > 0 ? gps2Num($exifCoord[0]) : 0;
	$minutes = count($exifCoord) > 1 ? gps2Num($exifCoord[1]) : 0;
	$seconds = count($exifCoord) > 2 ? gps2Num($exifCoord[2]) : 0;

	$flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

	return floatval($flip * ($degrees + ($minutes / 60) + ($seconds / 3600)));
}

function getGpstimestamp($exifCoord) {

	$hours   = count($exifCoord) > 0 ? gps2Num($exifCoord[0]) : 0;
	$minutes = count($exifCoord) > 1 ? gps2Num($exifCoord[1]) : 0;
	$seconds = count($exifCoord) > 2 ? gps2Num($exifCoord[2]) : 0;

	return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}


function gps2Num($coordPart) {

	$parts = explode('/', $coordPart);

	if (count($parts) <= 0)
		return 0;

	if (count($parts) == 1)
		return $parts[0];

	return floatval($parts[0]) / floatval($parts[1]);
}


function photo_profile_setperms($channel_id,$resource_id,$profile_id) {

	if(! $profile_id)
		return;

	$r = q("select profile_guid, is_default from profile where id = %d and uid = %d limit 1",
		dbesc($profile_id),
		intval($channel_id)
	);

	if(! $r)
		return;

	$is_default   = $r[0]['is_default'];
	$profile_guid = $r[0]['profile_guid'];

	if($is_default) {
		$r = q("update photo set allow_cid = '', allow_gid = '', deny_cid = '', deny_gid = ''
			where resource_id = '%s' and uid = %d",
			dbesc($resource_id),
			intval($channel_id)
		);
		$r = q("update attach set allow_cid = '', allow_gid = '', deny_cid = '', deny_gid = ''
			where hash = '%s' and uid = %d",
			dbesc($resource_id),
			intval($channel_id)
		);
	}
}

/**
 * @brief
 *
 * @param int $uid
 * @param int|string $profileid
 */
function profile_photo_set_profile_perms($uid, $profileid = 0) {

	$allowcid = '';

	if($profileid) {
		$r = q("SELECT photo, profile_guid, id, is_default, uid
			FROM profile WHERE uid = %d and ( profile.id = %d OR profile.profile_guid = '%s') LIMIT 1",
			intval($uid),
			intval($profileid),
			dbesc($profileid)
		);
	}
	else {
		logger('Resetting permissions on default-profile-photo for user ' . $uid);

		$r = q("SELECT photo, profile_guid, id, is_default, uid  FROM profile
			WHERE profile.uid = %d AND is_default = 1 LIMIT 1",
			intval($uid)
		); //If no profile is given, we update the default profile
	}
	if(! $r)
		return;

	$profile = $r[0];

	if($profile['id'] && $profile['photo']) {
		preg_match("@\w*(?=-\d*$)@i", $profile['photo'], $resource_id);
		$resource_id = $resource_id[0];

		if (! intval($profile['is_default'])) {
			$r0 = q("SELECT channel_hash FROM channel WHERE channel_id = %d LIMIT 1",
				intval($uid)
			);
			//Should not be needed in future. Catches old int-profile-ids.
			$r1 = q("SELECT abook.abook_xchan FROM abook WHERE abook_profile = '%d' ",
				intval($profile['id'])
			);
			$r2 = q("SELECT abook.abook_xchan FROM abook WHERE abook_profile = '%s'",
				dbesc($profile['profile_guid'])
			);
			$allowcid = "<" . $r0[0]['channel_hash'] . ">";
			foreach ($r1 as $entry) {
				$allowcid .= "<" . $entry['abook_xchan'] . ">";
			}
			foreach ($r2 as $entry) {
				$allowcid .= "<" . $entry['abook_xchan'] . ">";
			}

			q("UPDATE photo SET allow_cid = '%s' WHERE resource_id = '%s' AND uid = %d",
				dbesc($allowcid),
				dbesc($resource_id),
				intval($uid)
			);
		}
		else {
			// Reset permissions on default profile picture to public
			q("UPDATE photo SET allow_cid = '' WHERE photo_usage = %d AND uid = %d",
				intval(PHOTO_PROFILE),
				intval($uid)
			);
		}
	}
}

function fetch_image_from_url($url,&$mimetype) {

	$redirects = 0;
	$x = z_fetch_url($url,true,$redirects,[ 'novalidate' => true ]);
	if($x['success']) {
		$ht = new HTTPHeaders($x['header']);
		$hdrs = $ht->fetcharr();
		if ($hdrs && array_key_exists('content-type', $hdrs)) {
			$mimetype = $hdrs['content-type'];
		}

		return $x['body'];
	}

	return EMPTY_STR;
}


function isAnimatedGif($fileName)
{
    $fh = fopen($fileName, 'rb');

    if (!$fh) {
        return false;
    }

    $totalCount = 0;
    $chunk = '';

    // An animated gif contains multiple "frames", with each frame having a header made up of:
    // * a static 4-byte sequence (\x00\x21\xF9\x04)
    // * 4 variable bytes
    // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

    // We read through the file until we reach the end of it, or we've found at least 2 frame headers.
    while (!feof($fh) && $totalCount < 2) {
        // Read 100kb at a time and append it to the remaining chunk.
        $chunk .= fread($fh, 1024 * 100);
        $count = preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
        $totalCount += $count;

        // Execute this block only if we found at least one match,
        // and if we did not reach the maximum number of matches needed.
        if ($count > 0 && $totalCount < 2) {
            // Get the last full expression match.
            $lastMatch = end($matches[0]);
            // Get the string after the last match.
            $end = strrpos($chunk, $lastMatch) + strlen($lastMatch);
            $chunk = substr($chunk, $end);
        }
    }

    fclose($fh);

    return $totalCount > 1;
}

