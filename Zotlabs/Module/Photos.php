<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Lib\PermissionDescription;
use Zotlabs\Web\Controller;
use Zotlabs\Access\AccessControl;
use Zotlabs\Daemon\Run;

require_once('include/photo_factory.php');
require_once('include/photos.php');
require_once('include/acl_selectors.php');
require_once('include/bbcode.php');
require_once('include/security.php');
require_once('include/attach.php');
require_once('include/text.php');
require_once('include/conversation.php');


class Photos extends Controller {

	function init() {
	
		if (observer_prohibited()) {
			return;
		}
	
		if (argc() > 1) {

			$nick = escape_tags(argv(1));
	
			Libprofile::load($nick);
	
			$channelx = channelx_by_nick($nick);

			$profile_uid = 0;
	
			if ($channelx) {
				App::$data['channel']  = $channelx;
				head_set_icon($channelx['xchan_photo_s']);
				$profile_uid = $channelx['channel_id'];
			}
	
			App::$page['htmlhead'] .= "<script>var profile_uid = $profile_uid;</script>" ;
			App::$data['observer'] = App::get_observer();
		}
	}
	
	
	
	function post() {
	
		logger('mod-photos: photos_post: begin' , LOGGER_DEBUG);
	
		logger('mod_photos: REQUEST ' . print_r($_REQUEST,true), LOGGER_DATA);
		logger('mod_photos: FILES '   . print_r($_FILES,true), LOGGER_DATA);
	
		$ph = photo_factory('');
	
		$phototypes = $ph->supportedTypes();
	
		$page_owner_uid = App::$data['channel']['channel_id'];
	
		if (! perm_is_allowed($page_owner_uid,get_observer_hash(),'write_storage')) {
			notice( t('Permission denied.') . EOL );
			killme_if_ajax();
			return;
		}
	
		$acl = new AccessControl(App::$data['channel']);
	
		if ((argc() > 3) && (argv(2) === 'album')) {
	
			$album = argv(3);

			if (! photos_album_exists($page_owner_uid, get_observer_hash(), $album)) {
				notice( t('Album not found.') . EOL);
				goaway(z_root() . '/' . $_SESSION['photo_return']);
			}
	
	
			/*
			 * DELETE photo album and all its photos
			 */
	
			if ($_REQUEST['dropalbum'] === t('Delete Album')) {
	
				$folder_hash = '';
	 
				$r = q("select hash from attach where is_dir = 1 and uid = %d and hash = '%s'",
					intval($page_owner_uid),
					dbesc($album)
				);
				if (! $r) {
					notice( t('Album not found.') . EOL);
					return;
				}
				$folder_hash = $r[0]['hash'];
	
	
				$res = [];
				$admin_delete = false;

				// get the list of photos we are about to delete
	
				if (remote_channel() && (! local_channel())) {
					$str = photos_album_get_db_idstr($page_owner_uid,$album,remote_channel());
				}
				elseif (local_channel()) {
					$str = photos_album_get_db_idstr(local_channel(),$album);
				}
				elseif (is_site_admin()) {
					$str = photos_album_get_db_idstr_admin($page_owner_uid,$album);
					$admin_delete = true;
				}
				else {
					$str = null;
				}
				
				if (! $str) {
					goaway(z_root() . '/' . $_SESSION['photo_return']);
				}
	
				$r = q("select id from item where resource_id in ( $str ) and resource_type = 'photo' and uid = %d " . item_normal(),
					intval($page_owner_uid)
				);
				if($r) {
					foreach($r as $rv) {
						attach_delete($page_owner_uid, $rv['resource_id'], true );
					}
				}
	
				// remove the associated photos in case they weren't attached to an item (rare)
	
				q("delete from photo where resource_id in ( $str ) and uid = %d",
					intval($page_owner_uid)
				);

				q("delete from attach where hash in ( $str ) and uid = %d",
					intval($page_owner_uid)
				);
	
				if ($folder_hash) {
					attach_delete($page_owner_uid, $folder_hash, true );

					// Sync this action to channel clones, UNLESS it was an admin delete action.
					// The admin only has authority to moderate content on their own site.
					
					if (! $admin_delete) {	
						$sync = attach_export_data(App::$data['channel'],$folder_hash, true);
						if ($sync) {
							Libsync::build_sync_packet($page_owner_uid, [ 'file' => [ $sync ] ]);
						}
					}
				}
			}			
			goaway(z_root() . '/photos/' . App::$data['channel']['channel_address']);
		}
	
		if ((argc() > 2) && (x($_REQUEST,'delete')) && ($_REQUEST['delete'] === t('Delete Photo'))) {
			// same as above but remove single photo

			$ob_hash = get_observer_hash();

			if (! $ob_hash) {
				goaway(z_root() . '/' . $_SESSION['photo_return']);
			}

			// query to verify ownership of the photo by this viewer
			// We've already checked observer permissions to perfom this action
			
			// This implements the policy that remote channels (visitors and guests)
			// which modify content  can only modify their own content.
			// The page owner can modify anything within their authority, including
			// content published by others in their own channel pages.
			// The site admin can of course modify anything on their own site for
			// maintenance or legal compliance reasons. 
			
			$r = q("SELECT id, resource_id FROM photo WHERE ( xchan = '%s' or uid = %d ) AND resource_id = '%s' LIMIT 1",
				dbesc($ob_hash),
				intval(local_channel()),
				dbesc(argv(2))
			);
	
			if ($r) {
				attach_delete($page_owner_uid, $r[0]['resource_id'], true );
				$sync = attach_export_data(App::$data['channel'],$r[0]['resource_id'], true);
				if ($sync) {
					Libsync::build_sync_packet($page_owner_uid, [  'file' => [ $sync ] ]);
				}
			}
			elseif (is_site_admin()) {
				// If the admin deletes a photo, don't check ownership or invoke clone sync
				attach_delete($page_owner_uid, argv(2), true);
			}	

			goaway(z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/album/' . $_SESSION['album_return']);
		}

		// perform move_to_album
		
		if ((argc() > 2) && array_key_exists('move_to_album',$_POST)) {

			$m = q("select folder from attach where hash = '%s' and uid = %d limit 1",
				dbesc(argv(2)),
				intval($page_owner_uid)
			);
			
			// we should sanitize the post variable, but probably pointless because the move
			// will fail if we can't find the target
			
			if (($m) && ($m[0]['folder'] != $_POST['move_to_album'])) {
				attach_move($page_owner_uid,argv(2),$_POST['move_to_album']);			

				$sync = attach_export_data(App::$data['channel'],argv(2),false);
				if ($sync) {
					Libsync::build_sync_packet($page_owner_uid, [ 'file' => [ $sync ] ]);
				}

				// return if this is the only thing being edited
				
				if (! ($_POST['desc'] && $_POST['newtag'])) {
					goaway(z_root() . '/' . $_SESSION['photo_return']);
				}
			}
		}

		// this still needs some work
		
		if(defined('FIXED')) {
		if((x($_POST,'rotate') !== false) && ( (intval($_POST['rotate']) == 1) || (intval($_POST['rotate']) == 2) )) {
			logger('rotate');

			$resource_id = argv(2);

			$r = q("select * from photo where resource_id = '%s' and uid = %d and imgscale = 0 limit 1",
				dbesc($resource_id),
				intval($page_owner_uid)
			);
			if ($r) {
					
				$ph = photo_factory(@file_get_contents(dbunescbin($r[0]['content'])), $r[0]['mimetype']);
				if ($ph && $ph->is_valid()) {
					$rotate_deg = ( (intval($_POST['rotate']) == 1) ? 270 : 90 );
					$ph->rotate($rotate_deg);

					$edited = datetime_convert();

					q("update attach set filesize = %d, edited = '%s' where hash = '%s' and uid = %d",
						strlen($ph->imageString()),
						dbescdate($edited),
						dbesc($resource_id),
						intval($page_owner_uid)
					);
						
					$ph->saveImage(dbunescbin($r[0]['content']));
					
					$arr = [ 
						'aid'          => get_account_id(),
						'uid'          => intval($page_owner_uid), 
						'resource_id'  => dbesc($resource_id),
						'filename'     => $r[0]['filename'],
						'imgscale'	   => 0,
						'album'        => $r[0]['album'],
						'os_path'      => $r[0]['os_path'],
						'os_storage'   => 1,
						'os_syspath'   => dbunescbin($r[0]['content']),
						'display_path' => $r[0]['display_path'],
						'photo_usage'  => PHOTO_NORMAL,
						'edited'	   => dbescdate($edited)
					];

					$ph->save($arr);

					unset($arr['os_syspath']);

					if($width > 1024 || $height > 1024) 
						$ph->scaleImage(1024);
					$ph->storeThumbnail($arr, PHOTO_RES_1024);

					if($width > 640 || $height > 640) 
						$ph->scaleImage(640);
					$ph->storeThumbnail($arr, PHOTO_RES_640);

					if($width > 320 || $height > 320) 
						$ph->scaleImage(320);
					$ph->storeThumbnail($arr, PHOTO_RES_320);
				}
			}
		}} // end FIXED


		// edit existing photo properties
		
		if (x($_POST,'item_id') !== false && intval($_POST['item_id'])) {

			$title = ((x($_POST,'title')) ? escape_tags(trim($_POST['title'])) : EMPTY_STR );
			$desc  = ((x($_POST,'desc'))  ? escape_tags(trim($_POST['desc'])) : EMPTY_STR );
			$body  = ((x($_POST,'body'))  ? trim($_POST['body'])    : EMPTY_STR);
			
			$item_id     = ((x($_POST,'item_id')) ? intval($_POST['item_id'])       : 0);
			$is_nsfw     = ((x($_POST,'adult'))   ? intval($_POST['adult'])         : 0);

			// convert any supplied posted permissions for storage
			
			$acl->set_from_array($_POST);
			$perm = $acl->get();
	
			$resource_id = argv(2);
	
			$p = q("SELECT mimetype, is_nsfw, filename, title, description, resource_id, imgscale, allow_cid, allow_gid, deny_cid, deny_gid FROM photo WHERE resource_id = '%s' AND uid = %d ORDER BY imgscale DESC",
				dbesc($resource_id),
				intval($page_owner_uid)
			);
			if ($p) {
				// update the photo structure with any of the changed elements which are common to all resolutions 	
				$r = q("UPDATE photo SET title = '%s', description = '%s', allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s' WHERE resource_id = '%s' AND uid = %d",
					dbesc($title),
					dbesc($desc),
					dbesc($perm['allow_cid']),
					dbesc($perm['allow_gid']),
					dbesc($perm['deny_cid']),
					dbesc($perm['deny_gid']),
					dbesc($resource_id),
					intval($page_owner_uid)
				);
			}
	
			$item_private = (($str_contact_allow || $str_group_allow || $str_contact_deny || $str_group_deny) ? true : false);
	
			$old_is_nsfw = $p[0]['is_nsfw'];
			if ($old_is_nsfw != $is_nsfw) {
				$r = q("update photo set is_nsfw = %d where resource_id = '%s' and uid = %d",
					intval($is_nsfw),
					dbesc($resource_id),
					intval($page_owner_uid)
				);
			}
	
			/* Don't make the item visible if the only change was the album name */
	
			$visibility = 0;
			if ($p[0]['description'] !== $desc || $p[0]['title'] !== $title || $body !== EMPTY_STR) {
				$visibility = 1;
			}

			$r = q("SELECT * FROM item WHERE id = %d AND uid = %d LIMIT 1",
				intval($item_id),
				intval($page_owner_uid)
			);
			if (! $r) {
				logger('linked photo item not found.');
				notice ( t('linked item not found.') . EOL);
				return;
			}

			$linked_item = array_shift($r);
			
			// extract the original footer text
			$footer_text = EMPTY_STR;
			$orig_text = $linked_item['body'];
			$matches = [];

			if (preg_match('/\[footer\](.*?)\[\/footer\]/ism',$orig_text,$matches)) {
				$footer_text = $matches[0];
			}
	
			$body = cleanup_bbcode($body);
			$tags = linkify_tags($body, $page_owner_uid);

			$post_tags = [];
			if ($tags) {
				foreach ($tags as $tag) {
					$success = $tag['success'];
					if ($success['replaced']) {
						// suppress duplicate mentions/tags
						$already_tagged = false;
						foreach ($post_tags as $pt) {
							if ($pt['term'] === $success['term'] && $pt['url'] === $success['url'] && intval($pt['ttype']) === intval($success['termtype'])) {
								$already_tagged = true;
								break;
							}
						}
						if ($already_tagged) {
							continue;
						}

						$post_tags[] = [
							'uid'   => $page_owner_uid, 
							'ttype' => $success['termtype'],
							'otype' => TERM_OBJ_POST,
							'term'  => $success['term'],
							'url'   => $success['url']
						];
					}
				}
			}
			if ($post_tags) {
				q("delete from term where otype = 1 and oid = %d",
					intval($linked_item['id'])
				);
				foreach($post_tags as $t) {
					q("insert into term (uid,oid,otype,ttype,term,url)
						values(%d,%d,%d,%d,'%s','%s') ",
		                intval($page_owner_uid),
	    	            intval($linked_item['id']),
	        	        intval(TERM_OBJ_POST),
       		    	    intval($t['ttype']),
               			dbesc($t['term']),
						dbesc($t['url'])
   	        		);
				}
			}

			$body = z_input_filter($body,'text/bbcode');

			$obj = EMPTY_STR;

			if (isset($linked_item['obj']) && strlen($linked_item['obj'])) {
				$obj = json_decode($linked_item['obj'],true);
					
				$obj['name'] = (($title) ? $title : $p[0]['filename']);
				$obj['summary'] = (($desc) ? $desc : $p[0]['filename']);
				$obj['updated'] = datetime_convert('UTC','UTC','now',ATOM_TIME);
				$obj['source'] = [ 'content' => $body, 'mediaType' => 'text/bbcode' ];
				$obj['content'] = bbcode($body . $footer_text, [ 'export' => true ]);
				if (isset($obj['url']) && is_array($obj['url'])) {
					for ($x = 0; $x < count($obj['url']); $x ++) {
						$obj['url'][$x]['summary'] = $obj['summary'];
					}
				}
				$obj = json_encode($obj);
			}

			// make sure the linked item has the same permissions as the photo regardless of any other changes
			$x = q("update item set allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s', title = '%s', obj = '%s', body = '%s', edited = '%s', item_private = %d where id = %d",
				dbesc($perm['allow_cid']),
				dbesc($perm['allow_gid']),
				dbesc($perm['deny_cid']),
				dbesc($perm['deny_gid']),
				dbesc(($desc) ? $desc : $p[0]['filename']),
				dbesc($obj),
				dbesc($body . $footer_text),
				dbesc(datetime_convert()),
				intval($acl->is_private()),
				intval($item_id)
			);
	
			// make sure the attach has the same permissions as the photo regardless of any other changes
			$x = q("update attach set allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s' where hash = '%s' and uid = %d and is_photo = 1",
				dbesc($perm['allow_cid']),
				dbesc($perm['allow_gid']),
				dbesc($perm['deny_cid']),
				dbesc($perm['deny_gid']),
				dbesc($resource_id),
				intval($page_owner_uid)
			);
		

			if($visibility) {
				Run::Summon( [ 'Notifier', 'edit_post', $item_id ] );
			}

			$sync = attach_export_data(App::$data['channel'],$resource_id);
	
			if($sync) 
				Libsync::build_sync_packet($page_owner_uid, [ 'file' => [ $sync ] ]);
		
			goaway(z_root() . '/' . $_SESSION['photo_return']);
			return; // NOTREACHED
	
		
		}
	
	
		/**
		 * default post action - upload a photo
		 */
	
		$channel  = App::$data['channel'];
		$observer = App::$data['observer'];
	
		$_REQUEST['source'] = 'photos';
		require_once('include/attach.php');
	
		if(! local_channel()) {
			$_REQUEST['contact_allow'] = expand_acl($channel['channel_allow_cid']);
			$_REQUEST['group_allow']   = expand_acl($channel['channel_allow_gid']);
			$_REQUEST['contact_deny']  = expand_acl($channel['channel_deny_cid']);
			$_REQUEST['group_deny']    = expand_acl($channel['channel_deny_gid']);
		}
	

		$matches = [];
		$partial = false;

		if(array_key_exists('HTTP_CONTENT_RANGE',$_SERVER)) {
			$pm = preg_match('/bytes (\d*)\-(\d*)\/(\d*)/',$_SERVER['HTTP_CONTENT_RANGE'],$matches);
			if($pm) {
				logger('Content-Range: ' . print_r($matches,true));
				$partial = true;
			}
		}

		if($partial) {
			$x = save_chunk($channel,$matches[1],$matches[2],$matches[3]);

			if($x['partial']) {
				header('Range: bytes=0-' . (($x['length']) ? $x['length'] - 1 : 0));
				json_return_and_die($x);
			}
			else {
				header('Range: bytes=0-' . (($x['size']) ? $x['size'] - 1 : 0));

				$_FILES['userfile'] = [
					'name'     => $x['name'],
					'type'     => $x['type'],
					'tmp_name' => $x['tmp_name'],
					'error'    => $x['error'],
					'size'     => $x['size']
				];
			}
		}
		else {	
			if(! array_key_exists('userfile',$_FILES)) {
				$_FILES['userfile'] = [
					'name'     => $_FILES['files']['name'],
					'type'     => $_FILES['files']['type'],
					'tmp_name' => $_FILES['files']['tmp_name'],
					'error'    => $_FILES['files']['error'],
					'size'     => $_FILES['files']['size']
				];
			}
		}

		$r = attach_store($channel,get_observer_hash(), '', $_REQUEST);
	
		if(! $r['success']) {
			notice($r['message'] . EOL);
			if (is_ajax()) {
				killme();
			}
			goaway(z_root() . '/photos/' . App::$data['channel']['channel_address']);
		}
		if ($r['success'] && ! intval($r['data']['is_photo'])) {
			notice( sprintf( t('%s: Unsupported photo type. Saved as file.'), escape_tags($r['data']['filename'])));
		}
		if (is_ajax()) {
			killme();
		}
		
		goaway(z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/album/' . $r['data']['folder']);
	
	}
	
	
	
	function get() {
	
		// URLs:
		// photos/name
		// photos/name/album/xxxxx (xxxxx is album name)
		// photos/name/image/xxxxx
	
	
		if(observer_prohibited()) {
			notice( t('Public access denied.') . EOL);
			return;
		}
	
		$unsafe = 1 - get_safemode();
			
	
		if(! x(App::$data,'channel')) {
			notice( t('No photos selected') . EOL );
			return;
		}
	
		$ph = photo_factory('');
		$phototypes = $ph->supportedTypes();
	
		$_SESSION['photo_return'] = App::$cmd;
	
		//
		// Parse arguments 
		//
	
		$can_comment = perm_is_allowed(App::$profile['profile_uid'],get_observer_hash(),'post_comments');
	
		if(argc() > 3) {
			$datatype = argv(2);
			$datum = argv(3);
		}
		else {
			if(argc() > 2) {
				$datatype = argv(2);
				$datum = '';
			}
			else
				$datatype = 'summary';
		}
	
		if(argc() > 4)
			$cmd = argv(4);
		else
			$cmd = 'view';
	
		//
		// Setup permissions structures
		//
	
		$can_post       = false;
		$visitor        = 0;
	
	
		$owner_uid = App::$data['channel']['channel_id'];
		$owner_aid = App::$data['channel']['channel_account_id'];
	
		$observer = App::get_observer();
	
		$can_post = perm_is_allowed($owner_uid,$observer['xchan_hash'],'write_storage');
		$can_view = perm_is_allowed($owner_uid,$observer['xchan_hash'],'view_storage');
	
		if(! $can_view) {
			notice( t('Access to this item is restricted.') . EOL);
			return;
		}
	
		$sql_item = item_permissions_sql($owner_uid,get_observer_hash());
		$sql_extra = permissions_sql($owner_uid,get_observer_hash(),'photo');
		$sql_attach = permissions_sql($owner_uid,get_observer_hash(),'attach');

		nav_set_selected('Photos');
	
		$o = '<script src="vendor/blueimp/jquery-file-upload/js/vendor/jquery.ui.widget.js"></script>
			<script src="vendor/blueimp/jquery-file-upload/js/jquery.iframe-transport.js"></script>
			<script src="vendor/blueimp/jquery-file-upload/js/jquery.fileupload.js"></script>';


		$o .= "<script> var profile_uid = " . App::$profile['profile_uid'] 
			. "; var netargs = '?f='; var profile_page = " . App::$pager['page'] . "; </script>\r\n";
	
		$_is_owner = (local_channel() && (local_channel() == $owner_uid));
	
		/**
		 * Display upload form
		 */
	
		if ($can_post) {
	
			$uploader = '';
	
			$ret = array('post_url' => z_root() . '/photos/' . App::$data['channel']['channel_address'],
					'addon_text' => $uploader,
					'default_upload' => true);
	
			call_hooks('photo_upload_form',$ret);
	
			/* Show space usage */
	
			$r = q("select sum(filesize) as total from photo where aid = %d and imgscale = 0 ",
				intval(App::$data['channel']['channel_account_id'])
			);
	
	
			$limit = engr_units_to_bytes(service_class_fetch(App::$data['channel']['channel_id'],'photo_upload_limit'));
			if($limit !== false) {
				$usage_message = sprintf( t("%1$.2f MB of %2$.2f MB photo storage used."), $r[0]['total'] / 1024000, $limit / 1024000 );
			}
			else {
				$usage_message = sprintf( t('%1$.2f MB photo storage used.'), $r[0]['total'] / 1024000 );
	 		}
	
			if($_is_owner) {
				$channel = App::get_channel();
	
				$acl = new AccessControl($channel);
				$channel_acl = $acl->get();
	
				$lockstate = (($acl->is_private()) ? 'lock' : 'unlock');
			}
	
			$aclselect = (($_is_owner) ? populate_acl($channel_acl,false, PermissionDescription::fromGlobalPermission('view_storage')) : '');
	
			// this is wrong but is to work around an issue with js_upload wherein it chokes if these variables
			// don't exist. They really should be set to a parseable representation of the channel's default permissions 
			// which can be processed by getSelected() 
	
			if(! $aclselect) {
				$aclselect = '<input id="group_allow" type="hidden" name="allow_gid[]" value="" /><input id="contact_allow" type="hidden" name="allow_cid[]" value="" /><input id="group_deny" type="hidden" name="deny_gid[]" value="" /><input id="contact_deny" type="hidden" name="deny_cid[]" value="" />';
			}

			$selname = '';

			if($datum) {
				$h = attach_by_hash_nodata($datum,get_observer_hash());
				$selname = $h['data']['display_path'];
			}	

	
			$albums = ((array_key_exists('albums', App::$data)) ? App::$data['albums'] : photos_albums_list(App::$data['channel'],App::$data['observer']));
	
			if(! $selname) {
				$def_album = get_pconfig(App::$data['channel']['channel_id'],'system','photo_path');
				if($def_album) {
					$selname = filepath_macro($def_album);
					$albums['album'][] = array('text' => $selname);
				}
			}
	
			$tpl = get_markup_template('photos_upload.tpl');
			$upload_form = replace_macros($tpl,array(
				'$pagename' => t('Upload Photos'),
				'$sessid' => session_id(),
				'$usage' => $usage_message,
				'$nickname' => App::$data['channel']['channel_address'],
				'$newalbum_label' => t('Enter an album name'),
				'$newalbum_placeholder' => t('or select an existing album (doubleclick)'),
				'$visible' => array('visible', t('Create a status post for this upload'), 0, t('If multiple files are selected, the message will be repeated for each photo'), array(t('No'), t('Yes')), 'onclick="showHideBodyTextarea();"'),
				'$caption' => array('description', t('Please briefly describe this photo for vision-impaired viewers')),
				'title' => [ 'title', t('Title (optional)') ],
				'$body' => array('body', t('Your message (optional)'),'', 'This will only appear in the status post'),
				'$albums' => $albums['albums'],
				'$selname' => $selname,
				'$permissions' => t('Permissions'),
				'$aclselect' => $aclselect,
				'$allow_cid' => acl2json($channel_acl['allow_cid']),
				'$allow_gid' => acl2json($channel_acl['allow_gid']),
				'$deny_cid' => acl2json($channel_acl['deny_cid']),
				'$deny_gid' => acl2json($channel_acl['deny_gid']),
				'$lockstate' => $lockstate,
				'$uploader' => $ret['addon_text'],
				'$default' => (($ret['default_upload']) ? true : false),
				'$uploadurl' => $ret['post_url'],
				'$submit' => t('Upload')
	
			));
	
		}
	
		//
		// dispatch request
		//
	
		/*
		 * Display a single photo album
		 */
	
		if($datatype === 'album') {

			head_add_link([ 
				'rel'   => 'alternate',
				'type'  => 'application/json+oembed',
				'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
				'title' => 'oembed'
			]);

			if($x = photos_album_exists($owner_uid, get_observer_hash(), $datum)) {
				App::set_pager_itemspage(60);
				$album = $x['display_path'];
			} 
			else {
				goaway(z_root() . '/photos/' . App::$data['channel']['channel_address']);
			}

			if($_GET['order'] === 'posted')
				$order = 'created ASC';
			elseif($_GET['order'] === 'name')
				$order = 'filename ASC';
			else
				$order = 'created DESC';

			$r = q("SELECT p.resource_id, p.id, p.filename, p.mimetype, p.imgscale, p.description, p.created FROM photo p INNER JOIN
					(SELECT resource_id, max(imgscale) imgscale FROM photo left join attach on folder = '%s' and photo.resource_id = attach.hash WHERE attach.uid = %d AND imgscale <= 4 AND photo_usage IN ( %d, %d, %d ) and is_nsfw = %d $sql_extra GROUP BY resource_id) ph 
					ON (p.resource_id = ph.resource_id AND p.imgscale = ph.imgscale)
				ORDER BY $order LIMIT %d OFFSET %d",
				dbesc($x['hash']),
				intval($owner_uid),
				intval(PHOTO_NORMAL),
				intval(PHOTO_PROFILE),
				intval(PHOTO_COVER),
				intval($unsafe),
				intval(App::$pager['itemspage']),
				intval(App::$pager['start'])
			);

			// edit album name
			$album_edit = null;

			if($can_post) {
				$album_e = $album;
				$albums = ((array_key_exists('albums', App::$data)) ? App::$data['albums'] : photos_albums_list(App::$data['channel'],App::$data['observer']));
	
				// @fixme - syncronise actions with DAV
		
	//				$edit_tpl = get_markup_template('album_edit.tpl');
	//				$album_edit = replace_macros($edit_tpl,array(
	//					'$nametext' => t('Enter a new album name'),
	//					'$name_placeholder' => t('or select an existing one (doubleclick)'),
	//					'$nickname' => App::$data['channel']['channel_address'],
	//					'$album' => $album_e,
	//					'$albums' => $albums['albums'],
	//					'$hexalbum' => bin2hex($album),
	//					'$submit' => t('Submit'),
	//					'$dropsubmit' => t('Delete Album')
	//				));
	
			}
	
			$order =  [
				[ t('Date descending'), z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/album/' . $datum ],
				[ t('Date ascending'), z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/album/' . $datum . '?f=&order=posted'],
				[ t('Name ascending'), z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/album/' . $datum . '?f=&order=name']
			];
				
	
			$photos = [];
			if(count($r)) {
				$twist = 'rotright';
				foreach($r as $rr) {
	
					if($twist == 'rotright')
						$twist = 'rotleft';
					else
						$twist = 'rotright';
					
					$ext = $phototypes[$rr['mimetype']];
	
					$imgalt_e = $rr['filename'];
					$desc_e = $rr['description'];
	
					$imagelink = (z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/image/' . $rr['resource_id']
					. (($_GET['order'] === 'posted') ? '?f=&order=posted' : ''));
	
					$photos[] = array(
						'id' => $rr['id'],
						'twist' => ' ' . $twist . rand(2,4),
						'link' => $imagelink,
						'title' => t('View Photo'),
						'src' => z_root() . '/photo/' . $rr['resource_id'] . '-' . $rr['imgscale'] . '.' .$ext,
						'alt' => $imgalt_e,
						'desc'=> $desc_e,
						'ext' => $ext,
						'hash'=> $rr['resource_id'],
						'unknown' => t('Unknown')
					);
				}
			}
	
			if($_REQUEST['aj']) {
				if($photos) {
					$o = replace_macros(get_markup_template('photosajax.tpl'),array(
						'$photos' => $photos,
						'$album_id' => $datum
					));
				}
				else {
					$o = '<div id="content-complete"></div>';
				}
				echo $o;
				killme();
			}
			else {
				$o .= "<script> var page_query = '" . escape_tags(urlencode($_GET['req'])) . "'; var extra_args = '" . extra_query_args() . "' ; </script>";
				$tpl = get_markup_template('photo_album.tpl');
				$o .= replace_macros($tpl, array(
					'$photos' => $photos,
					'$album' => $album,
					'$album_id' => $datum,
					'$file_view' => t('View files'),
					'$files_path' => z_root() . '/cloud/' . App::$data['channel']['channel_address'] . '/' . $x['display_path'],
					'$album_edit' => array(t('Edit Album'), $album_edit),
					'$can_post' => $can_post,
					'$upload' => array(t('Add Photos'), z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/upload/' . $datum),
					'$order' => $order,
					'$sort'  => t('Sort'),
					'$upload_form' => $upload_form,
					'$usage' => $usage_message
				));

				return $o;	
			}
		}	
	
		/** 
		 * Display one photo
		 */
	
		if($datatype === 'image') {

			head_add_link([ 
				'rel'   => 'alternate',
				'type'  => 'application/json+oembed',
				'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
				'title' => 'oembed'
			]);

			$x = q("select folder from attach where hash = '%s' and uid = %d $sql_attach limit 1",
				dbesc($datum),
				intval($owner_uid)
			);

			// fetch image, item containing image, then comments
	
			$ph = q("SELECT id,aid,uid,xchan,resource_id,created,edited,title,description,album,filename,mimetype,height,width,filesize,imgscale,photo_usage,is_nsfw,allow_cid,allow_gid,deny_cid,deny_gid FROM photo WHERE uid = %d AND resource_id = '%s' 
				$sql_extra ORDER BY imgscale ASC ",
				intval($owner_uid),
				dbesc($datum)
			);
	
			if(! ($ph && $x)) {
	
				/* Check again - this time without specifying permissions */
	
				$ph = q("SELECT id FROM photo WHERE uid = %d AND resource_id = '%s' LIMIT 1",
					intval($owner_uid),
					dbesc($datum)
				);
				if($ph) 
					notice( t('Permission denied. Access to this item may be restricted.') . EOL);
				else
					notice( t('Photo not available') . EOL );
				return;
			}
	
	
	
			$prevlink = '';
			$nextlink = '';
	
			if($_GET['order'] === 'posted')
				$order = 'created ASC';
			elseif ($_GET['order'] === 'name')
				$order = 'filename ASC';
			else
				$order = 'created DESC';
	

			$prvnxt = q("SELECT hash FROM attach WHERE folder = '%s' AND uid = %d AND is_photo = 1
				$sql_attach ORDER BY $order ",
				dbesc($x[0]['folder']),
				intval($owner_uid)
			); 

			if(count($prvnxt)) {
				for($z = 0; $z < count($prvnxt); $z++) {
					if($prvnxt[$z]['hash'] == $ph[0]['resource_id']) {
						$prv = $z - 1;
						$nxt = $z + 1;
						if($prv < 0)
							$prv = count($prvnxt) - 1;
						if($nxt >= count($prvnxt))
							$nxt = 0;
						break;
					}
				}
	
				$prevlink = z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/image/' . $prvnxt[$prv]['hash'] . (($_GET['order']) ? '?f=&order=' . $_GET['order'] : '');
				$nextlink = z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/image/' . $prvnxt[$nxt]['hash'] . (($_GET['order']) ? '?f=&order=' . $_GET['order'] : '');
	 		}
	
	
			if(count($ph) == 1)
				$hires = $lores = $ph[0];
			if(count($ph) > 1) {
				if($ph[1]['imgscale'] == 2) {
					// original is 640 or less, we can display it directly
					$hires = $lores = $ph[0];
				}
				else {
				$hires = $ph[0];
				$lores = $ph[1];
				}
			}
	
			$album_link = z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/album/' . $x[0]['folder'];
	 		$tools = Null;
	 		$lock = Null;
	 
			if($can_post && ($ph[0]['uid'] == $owner_uid)) {
				$tools = array(
					'profile'=>array(z_root() . '/profile_photo/use/'.$ph[0]['resource_id'], t('Use as profile photo')),
					'cover'=>array(z_root() . '/cover_photo/use/'.$ph[0]['resource_id'], t('Use as cover photo')),
				);
			}
	
			// lockstate
			$lockstate = ( ( (strlen($ph[0]['allow_cid']) || strlen($ph[0]['allow_gid'])
					|| strlen($ph[0]['deny_cid']) || strlen($ph[0]['deny_gid'])) )
					? array('lock', t('Private Photo'))
					: array('unlock', Null));
	
			App::$page['htmlhead'] .= '<script>$(document).keydown(function(event) {' . "\n";
			if($prevlink)
				App::$page['htmlhead'] .= 'if(event.ctrlKey && event.keyCode == 37) { event.preventDefault(); window.location.href = \'' . $prevlink . '\'; }' . "\n";
			if($nextlink)
				App::$page['htmlhead'] .= 'if(event.ctrlKey && event.keyCode == 39) { event.preventDefault(); window.location.href = \'' . $nextlink . '\'; }' . "\n";
			App::$page['htmlhead'] .= '});</script>';
	
			if($prevlink)
				$prevlink = array($prevlink, t('Previous'));
	
			$photo = array(
				'href' => z_root() . '/photo/' . $hires['resource_id'] . '-' . $hires['imgscale'] . '.' . $phototypes[$hires['mimetype']],
				'title'=> t('View Full Size'),
				'src'  => z_root() . '/photo/' . $lores['resource_id'] . '-' . $lores['imgscale'] . '.' . $phototypes[$lores['mimetype']] . '?f=&_u=' . datetime_convert('','','','ymdhis')
			);
	
			if($nextlink)
				$nextlink = array($nextlink, t('Next'));
	
	
			// Do we have an item for this photo?
	
			$linked_items = q("SELECT * FROM item WHERE resource_id = '%s' and resource_type = 'photo' and uid = %d
				$sql_item LIMIT 1",
				dbesc($datum),
				intval($owner_uid)
			);
	
			$map = null;
			$link_item = null;
			
			if($linked_items) {
	
				xchan_query($linked_items);
				$linked_items = fetch_post_tags($linked_items,true);
	
				$link_item = $linked_items[0];
				$item_normal = item_normal();
	
				$r = q("select * from item where parent_mid = '%s' 
					$item_normal and uid = %d $sql_item ",
					dbesc($link_item['mid']),
					intval($link_item['uid'])
	
				);
	
				if($r) {
					xchan_query($r);
					$items = fetch_post_tags($r,true);
					$sorted_items = conv_sort($items,'commented');
				}
	
				$tags = [];
				if($link_item['term']) {
					$cnt = 0;
					foreach($link_item['term'] as $t) {
						$tags[$cnt] = array(0 => format_term_for_display($t));
						if($can_post && ($ph[0]['uid'] == $owner_uid)) {
							$tags[$cnt][1] = 'tagrm/drop/' . $link_item['id'] . '/' . bin2hex($t['term']);   //?f=&item=' . $link_item['id'];
							$tags[$cnt][2] = t('Remove');
						}
						$cnt ++;
					}
				}
	
				if((local_channel()) && (local_channel() == $link_item['uid'])) {
					q("UPDATE item SET item_unseen = 0 WHERE parent = %d and uid = %d and item_unseen = 1",
						intval($link_item['parent']),
						intval(local_channel())
					);
				}
	
				if($link_item['coord'] && Apps::system_app_installed($owner_uid,'Photomap')) {
					$map = generate_map($link_item['coord']);
				}
			}
	
	//		logger('mod_photo: link_item' . print_r($link_item,true));
	
			// FIXME - remove this when we move to conversation module 
	
			$comment_items = $sorted_items[0]['children'];

			$edit = null;
			if($can_post) {

				$album_e = $ph[0]['album'];
				$caption_e = $ph[0]['description'];
				$aclselect_e = (($_is_owner) ? populate_acl($ph[0], true, PermissionDescription::fromGlobalPermission('view_storage')) : '');
				$albums = ((array_key_exists('albums', App::$data)) ? App::$data['albums'] : photos_albums_list(App::$data['channel'],App::$data['observer']));
	
				$_SESSION['album_return'] = bin2hex($ph[0]['album']);

				$folder_list = attach_folder_select_list($ph[0]['uid']);
				$edit_body = htmlspecialchars_decode(undo_post_tagging($link_item['body']),ENT_COMPAT);
				// We will regenerate the body footer
				$edit_body = preg_replace('/\[footer\](.*?)\[\/footer\]/ism','',$edit_body);

				$edit = [
					'edit'                 => t('Edit photo'),
					'id'                   => $link_item['id'],
					'albums'               => $albums['albums'],
					'album'                => $album_e,
					'album_select'         => [ 'move_to_album', t('Move photo to album'), $x[0]['folder'], '', $folder_list ],
					'newalbum_label'       => t('Enter a new album name'),
					'newalbum_placeholder' => t('or select an existing one (doubleclick)'),
					'nickname'             => App::$data['channel']['channel_address'],
					'resource_id'          => $ph[0]['resource_id'],
					'desc'                 => [ 'desc', t('Please briefly describe this photo for vision-impaired viewers'), $ph[0]['description'] ],
					'title'                => [ 'title', t('Title (optional)'), $ph[0]['title'] ],
					'body'                 => [ 'body', t('Your message (optional)'),$edit_body, t('This will only appear in the optional status post attached to this photo') ],
					'tag_label'            => t('Add a Tag'),
					'permissions'          => t('Permissions'),
					'aclselect'            => $aclselect_e,
					'allow_cid'            => acl2json($ph[0]['allow_cid']),
					'allow_gid'            => acl2json($ph[0]['allow_gid']),
					'deny_cid'             => acl2json($ph[0]['deny_cid']),
					'deny_gid'             => acl2json($ph[0]['deny_gid']),
					'lockstate'            => $lockstate[0],
					'help_tags'            => t('Example: @bob, @Barbara_Jensen, @jim@example.com'),
					'item_id'              => ((count($linked_items)) ? $link_item['id'] : 0),
					'adult_enabled'        => feature_enabled($owner_uid,'adult_photo_flagging'),
					'adult'                => array('adult',t('Flag as adult in album view'), intval($ph[0]['is_nsfw']),''),
					'submit'               => t('Submit'),
					'delete'               => t('Delete Photo'),
					'expandform'	       => ((x($_GET,'expandform')) ? true : false)
				];
			}
	
			if(count($linked_items)) {
	
				$cmnt_tpl = get_markup_template('comment_item.tpl');
				$tpl = get_markup_template('photo_item.tpl');
				$return_url = App::$cmd;
	
				$like_tpl = get_markup_template('like_noshare.tpl');
	
				$likebuttons = '';
				$ilike = false;
				$inolike = false;


				if ($items) {
					foreach ($items as $i) {
						if ($i['verb'] === 'Like' && $i['author_xchan'] === get_observer_hash() && $i['thr_parent'] = $link_item['mid']) {
							$ilike = true;
						}
						if ($i['verb'] === 'Dislike' && $i['author_xchan'] === get_observer_hash() && $i['thr_parent'] === $link_item['mid']) {
							$inolike = true;
						}
					}
				}
	
				if($observer && ($can_post || $can_comment)) {
					$likebuttons = [
						'id'       => $link_item['id'],
						'likethis' => t('I like this'),
						'ilike'    => $ilike,
						'inolike'  => $inolike,
						'nolike'   => t('I don\'t like this'),
						'unlikethis' => t('Undo like'),
						'unnolike' => t('Undo dislike'),
						'share'    => t('Share'),
						'wait'     => t('Please wait')
					];
				}

				$comments = '';
				if(! $comment_items) {
					if($observer && ($can_post || $can_comment)) {
						$commentbox = replace_macros($cmnt_tpl,array(
							'$return_path' => '', 
							'$mode' => 'photos',
							'$jsreload' => $return_url,
							'$type' => 'wall-comment',
							'$id' => $link_item['id'],
							'$parent' => $link_item['id'],
							'$profile_uid' =>  $owner_uid,
							'$mylink' => $observer['xchan_url'],
							'$mytitle' => t('This is you'),
							'$myphoto' => $observer['xchan_photo_s'],
							'$comment' => t('Comment'),
							'$submit' => t('Submit'),
							'$preview' => t('Preview'),
							'$auto_save_draft' => 'true',
							'$ww' => '',
							'$feature_encrypt' => false
						));
					}
				}
	
				$alike = [];
				$dlike = [];
				
				$like = '';
				$dislike = '';
	
				$conv_responses = [
					'like'        => [ 'title' => t('Likes','title') ],
					'dislike'     => [ 'title' => t('Dislikes','title') ],
					'attendyes'   => [ 'title' => t('Attending','title') ], 
					'attendno'    => [ 'title' => t('Not attending','title') ], 
					'attendmaybe' => [ 'title' => t('Might attend' ,'title') ]
				];
	
				if($r) {
	
					foreach($r as $item) {
						builtin_activity_puller($item, $conv_responses);
					}
	
					$like_count = ((x($alike,$link_item['mid'])) ? $alike[$link_item['mid']] : '');
					$like_list = ((x($alike,$link_item['mid'])) ? $alike[$link_item['mid'] . '-l'] : '');

					if(is_array($like_list) && (count($like_list) > MAX_LIKERS)) {
						$like_list_part = array_slice($like_list, 0, MAX_LIKERS);
						array_push($like_list_part, '<a href="#" data-toggle="modal" data-target="#likeModal-' . $this->get_id() . '"><b>' . t('View all') . '</b></a>');
					} else {
						$like_list_part = '';
					}
					$like_button_label = tt('Like','Likes',$like_count,'noun');
	
						$dislike_count = ((x($dlike,$link_item['mid'])) ? $dlike[$link_item['mid']] : '');
						$dislike_list = ((x($dlike,$link_item['mid'])) ? $dlike[$link_item['mid'] . '-l'] : '');
						$dislike_button_label = tt('Dislike','Dislikes',$dislike_count,'noun');
						if (is_array($dislike_list) && (count($dislike_list) > MAX_LIKERS)) {
							$dislike_list_part = array_slice($dislike_list, 0, MAX_LIKERS);
							array_push($dislike_list_part, '<a href="#" data-toggle="modal" data-target="#dislikeModal-' . $this->get_id() . '"><b>' . t('View all') . '</b></a>');
						} else {
							$dislike_list_part = '';
						}

	
	
					$like    = ((isset($alike[$link_item['mid']])) ? format_like($alike[$link_item['mid']],$alike[$link_item['mid'] . '-l'],'like',$link_item['mid']) : '');
					$dislike = ((isset($dlike[$link_item['mid']])) ? format_like($dlike[$link_item['mid']],$dlike[$link_item['mid'] . '-l'],'dislike',$link_item['mid']) : '');
	
					// display comments
	
					foreach ($comment_items as $item) {
						$comment = '';
						$template = $tpl;
						$sparkle = '';
	
						if (! visible_activity($item)) {
							continue;
						}
	
						$profile_url = zid($item['author']['xchan_url']);	
						$profile_name   = $item['author']['xchan_name'];
						$profile_avatar = $item['author']['xchan_photo_m'];
	
						$profile_link = $profile_url;
	
						$drop = '';
	
						if($observer['xchan_hash'] === $item['author_xchan'] || $observer['xchan_hash'] === $item['owner_xchan'])
							$drop = replace_macros(get_markup_template('photo_drop.tpl'), array('$id' => $item['id'], '$delete' => t('Delete')));
	
	
						$name_e = $profile_name;
						$title_e = $item['title'];
						unobscure($item);
						$body_e = prepare_text($item['body'],$item['mimetype']);
	
						$comments .= replace_macros($template,array(
							'$id' => $item['id'],
							'$mode' => 'photos',
							'$profile_url' => $profile_link,
							'$name' => $name_e,
							'$thumb' => $profile_avatar,
							'$sparkle' => $sparkle,
							'$title' => $title_e,
							'$body' => $body_e,
							'$ago' => relative_date($item['created']),
							'$indent' => (($item['parent'] != $item['id']) ? ' comment' : ''),
							'$drop' => $drop,
							'$comment' => $comment
						));
	
					}
				
					if($observer && ($can_post || $can_comment)) {
						$commentbox = replace_macros($cmnt_tpl,array(
							'$return_path' => '',
							'$jsreload' => $return_url,
							'$type' => 'wall-comment',
							'$id' => $link_item['id'],
							'$parent' => $link_item['id'],
							'$profile_uid' =>  $owner_uid,
							'$mylink' => $observer['xchan_url'],
							'$mytitle' => t('This is you'),
							'$myphoto' => $observer['xchan_photo_s'],
							'$comment' => t('Comment'),
							'$submit' => t('Submit'),
							'$ww' => ''
						));
					}
	
				}
				$paginate = paginate($a);
			}
			
			$album_e   = [ $album_link, $ph[0]['album'] ];
			$like_e    = $like;
			$dislike_e = $dislike;
	
	
			$response_verbs = array('like','dislike');
	
			$responses = get_responses($conv_responses,$response_verbs,'',$link_item);
	
			$o .= replace_macros(get_markup_template('photo_view.tpl'), [
				'$id'                   => $ph[0]['id'],
				'$album'                => $album_e,
				'$tools_label'          => t('Photo Tools'),
				'$tools'                => $tools,
				'$lock'                 => $lockstate[1],
				'$photo'                => $photo,
				'$prevlink'             => $prevlink,
				'$nextlink'             => $nextlink,
				'$title'                => $ph[0]['title'],
				'$desc'                 => $ph[0]['description'],
				'$filename'             => $ph[0]['filename'],
				'$unknown'              => t('Unknown'),
				'$tag_hdr'              => t('In This Photo:'),
				'$tags'                 => $tags,
				'responses'             => $responses,
				'$edit'                 => $edit,	
				'$map'                  => $map,
				'$map_text'             => t('Map'),
				'$likebuttons'          => $likebuttons,
				'$like'                 => $like_e,
				'$dislike'              => $dislike_e,
				'$like_count'           => $like_count,
				'$like_list'            => $like_list,
				'$like_list_part'       => $like_list_part,
				'$like_button_label'    => $like_button_label,
				'$like_modal_title'     => t('Likes','noun'),
				'$dislike_modal_title'  => t('Dislikes','noun'),
				'$dislike_count'        => $dislike_count,
				'$dislike_list'         => $dislike_list,
				'$dislike_list_part'    => $dislike_list_part,
				'$dislike_button_label' => $dislike_button_label,
				'$modal_dismiss'        => t('Close'),
				'$comments'             => $comments,
				'$commentbox'           => $commentbox,
				'$paginate'             => $paginate,
			]);
	
			App::$data['photo_html'] = $o;			
			return $o;
		}
	
		// Default - show recent photos 
	
		head_add_link([ 
			'rel'   => 'alternate',
			'type'  => 'application/json+oembed',
			'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
			'title' => 'oembed'
		]);

		App::set_pager_itemspage(60);
		
		$r = q("SELECT p.resource_id, p.id, p.filename, p.mimetype, p.album, p.imgscale, p.created, p.display_path FROM photo p 
			INNER JOIN ( SELECT resource_id, max(imgscale) imgscale FROM photo WHERE photo.uid = %d AND photo_usage IN ( %d, %d ) 
			AND is_nsfw = %d $sql_extra group by resource_id ) ph ON (p.resource_id = ph.resource_id and p.imgscale = ph.imgscale) 
			ORDER by p.created DESC LIMIT %d OFFSET %d",
			intval(App::$data['channel']['channel_id']),
			intval(PHOTO_NORMAL),
			intval(PHOTO_PROFILE),
			intval($unsafe),
			intval(App::$pager['itemspage']),
			intval(App::$pager['start'])
		);
	
		$photos = [];
		if($r) {
			$twist = 'rotright';
			foreach($r as $rr) {

				if(! attach_can_view_folder(App::$data['channel']['channel_id'],get_observer_hash(),$rr['resource_id']))
					continue;

				if($twist == 'rotright')
					$twist = 'rotleft';
				else
					$twist = 'rotright';
				$ext = $phototypes[$rr['mimetype']];
				
				$alt_e = $rr['filename'];
				$name_e = dirname($rr['display_path']);

				$photos[] = [
					'id'        => $rr['id'],
					'twist'     => ' ' . $twist . rand(2,4),
					'link'  	=> z_root() . '/photos/' . App::$data['channel']['channel_address'] . '/image/' . $rr['resource_id'],
					'title' 	=> t('View Photo'),
					'src'     	=> z_root() . '/photo/' . $rr['resource_id'] . '-' . ((($rr['imgscale']) == 6) ? 4 : $rr['imgscale']) . '.' . $ext,
					'alt'     	=> $alt_e,
					'album'	    => [ 'name' => $name_e ],
				];
			}
		}
		
		if($_REQUEST['aj']) {
			if($photos) {
				$o = replace_macros(get_markup_template('photosajax.tpl'), [
					'$photos' => $photos,
					'$album_id' => bin2hex(t('Recent Photos'))
				]);
			}
			else {
				$o = '<div id="content-complete"></div>';
			}
			echo $o;
			killme();
		}
		else {

			$o .= "<script>var page_query = '" . escape_tags($_GET['req']) . "'; var extra_args = '" . extra_query_args() . "' ; </script>";

			$o .= replace_macros(get_markup_template('photos_recent.tpl'), [
				'$title'       => t('Recent Photos'),
				'$album_id'    => bin2hex(t('Recent Photos')),
				'$file_view' => t('View files'),
				'$files_path' => z_root() . '/cloud/' . App::$data['channel']['channel_address'],
				'$can_post'    => $can_post,
				'$upload'      => t('Add Photos'),
				'$photos'      => $photos,
				'$upload_form' => $upload_form,
				'$usage'       => $usage_message
			]);

			return $o;
		}
	}
}
