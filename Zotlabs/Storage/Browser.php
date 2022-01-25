<?php
namespace Zotlabs\Storage;


use App;
use Sabre\DAV;
use Sabre\DAV\INode;
use Zotlabs\Lib\PermissionDescription;
use Zotlabs\Access\AccessControl;
use Zotlabs\Render\Theme;
use Zotlabs\Lib\Channel;
use Zotlabs\Lib\Navbar;
use function Sabre\HTTP\encodePath;

//require_once('include/conversation.php');
//require_once('include/text.php');

require_once('include/acl_selectors.php');


/**
 * @brief Provides a DAV frontend for the webbrowser.
 *
 * Browser is a SabreDAV server-plugin to provide a view to the DAV storage
 * for the webbrowser.
 *
 * @extends \Sabre\\DAV\\Browser\\Plugin
 *
 * @link http://framagit.org/hubzilla/core/
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */

class Browser extends DAV\Browser\Plugin {

	public $build_page = false;
	/**
	 * @see set_writeable()
	 * @see \\Sabre\\DAV\\Auth\\Backend\\BackendInterface
	 * @var BasicAuth $auth
	 */
	private $auth;

	/**
	 * @brief Constructor for Browser class.
	 *
	 * $enablePost will be activated through set_writeable() in a later stage.
	 * At the moment the write_storage permission is only valid for the whole
	 * folder. No file specific permissions yet.
	 * @todo disable enablePost by default and only activate if permissions
	 * grant edit rights.
	 *
	 * Disable assets with $enableAssets = false. Should get some thumbnail views
	 * anyway.
	 *
	 * @param BasicAuth &$auth
	 */
	public function __construct(&$auth) {
		$this->auth = $auth;
		parent::__construct(true, false);
	}

	/**
	 * The DAV browser is instantiated after the auth module and directory classes
	 * but before we know the current directory and who the owner and observer
	 * are. So we add a pointer to the browser into the auth module and vice versa.
	 * Then when we've figured out what directory is actually being accessed, we
	 * call the following function to decide whether or not to show web elements
	 * which include writeable objects.
	 *
	 * @fixme It only disable/enable the visible parts. Not the POST handler
	 * which handels the actual requests when uploading files or creating folders.
	 *
	 * @todo Maybe this whole way of doing this can be solved with some
	 * $server->subscribeEvent().
	 */
	public function set_writeable() {
		if (! $this->auth->owner_id) {
			$this->enablePost = false;
		}

		if (! perm_is_allowed($this->auth->owner_id, get_observer_hash(), 'write_storage')) {
			$this->enablePost = false;
		}
		else {
			$this->enablePost = true;
		}
	}

	/**
	 * @brief Creates the directory listing for the given path.
	 *
	 * @param string $path which should be displayed
	 */
	public function generateDirectoryIndex($path) {
		// (owner_id = channel_id) is visitor owner of this directory?
		$is_owner = ((local_channel() && $this->auth->owner_id == local_channel()) ? true : false);

		if ($this->auth->getTimezone()) {
			date_default_timezone_set($this->auth->getTimezone());
		}

		if ($this->auth->owner_nick) {
			$html = '';
		}

		$files = $this->server->getPropertiesForPath($path, [
			'{DAV:}displayname',
			'{DAV:}resourcetype',
			'{DAV:}getcontenttype',
			'{DAV:}getcontentlength',
			'{DAV:}getlastmodified',
			], 1);

		$parent = $this->server->tree->getNodeForPath($path);

		$parentpath = [];
		// only show parent if not leaving /cloud/; TODO how to improve this?
		if ($path && $path != "cloud") {
			list($parentUri) = \Sabre\Uri\split($path);
			$fullPath = encodePath($this->server->getBaseUri() . $parentUri);

			$parentpath['icon'] = $this->enableAssets ? '<a href="' . $fullPath . '"><img src="' . $this->getAssetUrl('icons/parent' . $this->iconExtension) . '" width="24" alt="' . t('parent') . '"></a>' : '';
			$parentpath['path'] = $fullPath;
		}

		$f = [];
		foreach ($files as $file) {
			$ft = [];
			$type = null;

			// This is the current directory, we can skip it
			if (rtrim($file['href'], '/') == $path) {
				continue;
			}

			list(, $name) = \Sabre\Uri\split($file['href']);

			if (isset($file[200]['{DAV:}resourcetype'])) {
				$type = $file[200]['{DAV:}resourcetype']->getValue();

				// resourcetype can have multiple values
				if (! is_array($type)) {
					$type = [ $type ];
				}

				foreach ($type as $k => $v) {
					// Some name mapping is preferred
					switch ($v) {
						case '{DAV:}collection' :
							$type[$k] = t('Collection');
							break;
						case '{DAV:}principal' :
							$type[$k] = t('Principal');
							break;
						case '{urn:ietf:params:xml:ns:carddav}addressbook' :
							$type[$k] = t('Addressbook');
							break;
						case '{urn:ietf:params:xml:ns:caldav}calendar' :
							$type[$k] = t('Calendar');
							break;
						case '{urn:ietf:params:xml:ns:caldav}schedule-inbox' :
							$type[$k] = t('Schedule Inbox');
							break;
						case '{urn:ietf:params:xml:ns:caldav}schedule-outbox' :
							$type[$k] = t('Schedule Outbox');
							break;
						case '{http://calendarserver.org/ns/}calendar-proxy-read' :
							$type[$k] = 'Proxy-Read';
							break;
						case '{http://calendarserver.org/ns/}calendar-proxy-write' :
							$type[$k] = 'Proxy-Write';
							break;
					}
				}
				$type = implode(', ', $type);
			}

			// If no resourcetype was found, we attempt to use
			// the contenttype property
			if (! $type && isset($file[200]['{DAV:}getcontenttype'])) {
				$type = $file[200]['{DAV:}getcontenttype'];
			}
			if (! $type) {
				$type = t('Unknown');
			}

			$size = isset($file[200]['{DAV:}getcontentlength']) ? (int)$file[200]['{DAV:}getcontentlength'] : '';
			$lastmodified = ((isset($file[200]['{DAV:}getlastmodified'])) ? $file[200]['{DAV:}getlastmodified']->getTime()->format('Y-m-d H:i:s') : '');

			$fullPath = encodePath('/' . trim($this->server->getBaseUri() . ($path ? $path . '/' : '') . $name, '/'));

			$displayName = isset($file[200]['{DAV:}displayname']) ? $file[200]['{DAV:}displayname'] : $name;

			$displayName = $this->escapeHTML($displayName);
			$type = $this->escapeHTML($type);


			$icon = '';

			if ($this->enableAssets) {
				$node = $this->server->tree->getNodeForPath(($path ? $path . '/' : '') . $name);
				foreach (array_reverse($this->iconMap) as $class=>$iconName) {
					if ($node instanceof $class) {
						$icon = '<a href="' . $fullPath . '"><img src="' . $this->getAssetUrl($iconName . $this->iconExtension) . '" alt="" width="24"></a>';
						break;
					}
				}
			}

			$folderHash = '';
			$parentHash = '';
			$owner = $this->auth->owner_id;
			$splitPath = explode('/', $fullPath);
			if (count($splitPath) > 3) {
				for ($i = 3; $i < count($splitPath); $i++) {
					$attachName = urldecode($splitPath[$i]);
					$folderHash = $parentHash;
					$attachHash = $this->findAttachHash($owner, $parentHash, $attachName);
					$parentHash = $attachHash;
				}
			}


			// generate preview icons for tile view. 
			// SVG, PDF and office documents have some security concerns and should only be allowed on single-user sites with tightly controlled
			// upload access. system.thumbnail_security should be set to 1 if you want to include these types 

			$is_creator = false;
			$photo_icon = '';
			$preview_style = intval(get_config('system','thumbnail_security',0));

			$r = q("select content, creator from attach where hash = '%s' and uid = %d limit 1",
				dbesc($attachHash),
				intval($owner)
			);

			if ($r) {
				$is_creator = (($r[0]['creator'] === get_observer_hash()) ? true : false);
			 	if (file_exists(dbunescbin($r[0]['content']) . '.thumb')) {
					$photo_icon = 'data:image/jpeg;base64,' . base64_encode(file_get_contents(dbunescbin($r[0]['content']) . '.thumb'));
				}
			}

			if (strpos($type,'image/') === 0 && $attachHash) {
				$r = q("select resource_id, imgscale from photo where resource_id = '%s' and imgscale in ( %d, %d ) order by imgscale asc limit 1",
					dbesc($attachHash),
					intval(PHOTO_RES_320),
					intval(PHOTO_RES_PROFILE_80)
				);
				if ($r) {
					$photo_icon = 'photo/' . $r[0]['resource_id'] . '-' . $r[0]['imgscale'];				
				}
				if ($type === 'image/svg+xml' && $preview_style > 0) {
					$photo_icon = $fullPath;
				}
			}

			$g = [ 'resource_id' => $attachHash, 'thumbnail' => $photo_icon, 'security' => $preview_style ];
			call_hooks('file_thumbnail', $g);
			$photo_icon = $g['thumbnail'];


			$attachIcon = ""; 

			// put the array for this file together
			$ft['attachId'] = $this->findAttachIdByHash($attachHash);
			$ft['fileStorageUrl'] = substr($fullPath, 0, strpos($fullPath, "cloud/")) . "filestorage/" . $this->auth->owner_nick;
			$ft['icon'] = $icon;
			$ft['photo_icon'] = $photo_icon;
			$ft['attachIcon'] = (($size) ? $attachIcon : '');
			// @todo Should this be an item value, not a global one?
			$ft['is_owner'] = $is_owner;
			$ft['is_creator'] = $is_creator;
			$ft['fullPath'] = $fullPath;
			$ft['displayName'] = $displayName;
			$ft['type'] = $type;
			$ft['size'] = $size;
			$ft['sizeFormatted'] = userReadableSize($size);
			$ft['lastmodified'] = (($lastmodified) ? datetime_convert('UTC', date_default_timezone_get(), $lastmodified) : '');
			$ft['iconFromType'] = getIconFromType($type);

			$f[] = $ft;

		}


		$output = '';
		if ($this->enablePost && $parentpath) {
			$this->server->emit('onHTMLActionsPanel', array($parent, &$output, $path));
		}

		// "display as tiles" is the default for visitors, and changes to this setting are stored in the session
		// so that they apply even to unauthenticated visitors.

		$deftiles = (($is_owner) ? 0 : 1);
		$tiles = ((array_key_exists('cloud_tiles',$_SESSION)) ? intval($_SESSION['cloud_tiles']) : $deftiles);
		$_SESSION['cloud_tiles'] = $tiles;
	
		$html .= replace_macros(get_markup_template('cloud.tpl'), [
				'$header' => t('Files') . ": " . $this->escapeHTML($path) . "/",
				'$total' => t('Total'),
				'$actionspanel' => $output,
				'$shared' => t('Shared'),
				'$create' => t('Create'),
				'$upload' => t('Add Files'),
				'$is_owner' => $is_owner,
				'$is_admin' => is_site_admin(),
				'$admin_delete' => t('Admin Delete'),
				'$parentpath' => $parentpath,
				'$cpath' => bin2hex(App::$query_string),
				'$tiles' => intval($_SESSION['cloud_tiles']),
				'$photo_view' => (($parentpath) ? t('View photos') : EMPTY_STR),
				'$photos_path' => z_root() . '/photos/' . $this->auth->owner_nick . '/album/' . $folderHash,
				'$entries' => $f,
				'$name' => t('Name'),
				'$type' => t('Type'),
				'$size' => t('Size'),
				'$lastmod' => t('Last Modified'),
				'$parent' => t('parent'),
				'$edit' => t('Edit'),
				'$delete' => t('Delete'),
				'$nick' => $this->auth->getCurrentUser()
			]
		);


		$a = false;

		nav_set_selected('Files');

		App::$page['content'] = $html;
		load_pdl();

		$current_theme = Theme::current();

		$theme_info_file = 'view/theme/' . $current_theme[0] . '/php/theme.php';
		if (file_exists($theme_info_file)) {
			require_once($theme_info_file);
			if (function_exists(str_replace('-', '_', $current_theme[0]) . '_init')) {
				$func = str_replace('-', '_', $current_theme[0]) . '_init';
				$func($a);
			}
		}
		$this->server->httpResponse->setHeader('Content-Security-Policy', "script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'");
		$this->build_page = true;
	}

	/**
	 * @brief Creates a form to add new folders and upload files.
	 *
	 * @param INode $node
	 * @param[in,out] string &$output
	 * @param string $path
	 */
	public function htmlActionsPanel(INode $node, &$output, $path) {
		if (! $node instanceof DAV\ICollection) {
			return;
		}

		// We also know fairly certain that if an object is a non-extended
		// SimpleCollection, we won't need to show the panel either.

		if (get_class($node) === 'Sabre\\DAV\\SimpleCollection') {
			return;
		}
		
		$aclselect = null;
		$lockstate = '';
		$limit = 0;

		if ($this->auth->owner_id) {
			$channel = Channel::from_id($this->auth->owner_id);
			if ($channel) {
				$acl = new AccessControl($channel);
				$channel_acl = $acl->get();
				$lockstate = (($acl->is_private()) ? 'lock' : 'unlock');

				$aclselect = ((local_channel() == $this->auth->owner_id) ? populate_acl($channel_acl,false,PermissionDescription::fromGlobalPermission('view_storage')) : '');
			}

			// Storage and quota for the account (all channels of the owner of this directory)!
			$limit = engr_units_to_bytes(service_class_fetch($this->auth->owner_id, 'attach_upload_limit'));
		}

		if ((! $limit) && get_config('system','cloud_report_disksize')) {
			$limit = engr_units_to_bytes(disk_free_space('store'));
		}

		$r = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d",
			intval($this->auth->channel_account_id)
		);
		$used = $r[0]['total'];
		if ($used) {
			$quotaDesc = t('You are using %1$s of your available file storage.');
			$quotaDesc = sprintf($quotaDesc,userReadableSize($used));
		}
		if ($limit && $used) {
			$quotaDesc = t('You are using %1$s of %2$s available file storage. (%3$s&#37;)');
			$quotaDesc = sprintf($quotaDesc,
				userReadableSize($used),
				userReadableSize($limit),
				round($used / $limit, 1) * 100);
		}
		// prepare quota for template
		$quota = [];
		$quota['used'] = $used;
		$quota['limit'] = $limit;
		$quota['desc'] = $quotaDesc;
		$quota['warning'] = ((($limit) && ((round($used / $limit, 1) * 100) >= 90)) ? t('WARNING:') : ''); // 10485760 bytes = 100MB

		// strip 'cloud/nickname', but only at the beginning of the path

		$special = 'cloud/' . $this->auth->owner_nick;
		$count   = strlen($special);

		if (strpos($path,$special) === 0) {
			$path = trim(substr($path,$count),'/');
		}

		$output .= replace_macros(get_markup_template('cloud_actionspanel.tpl'), array(
				'$folder_header' => t('Create new folder'),
				'$folder_submit' => t('Create'),
				'$upload_header' => t('Upload file'),
				'$upload_submit' => t('Upload'),
				'$quota' => $quota,
				'$channick' => $this->auth->owner_nick,
				'$aclselect' => $aclselect,
				'$allow_cid' => acl2json($channel_acl['allow_cid']),
				'$allow_gid' => acl2json($channel_acl['allow_gid']),
				'$deny_cid' => acl2json($channel_acl['deny_cid']),
				'$deny_gid' => acl2json($channel_acl['deny_gid']),
				'$lockstate' => $lockstate,
				'$return_url' => App::$cmd,
				'$path' => $path,
				'$folder' => find_folder_hash_by_path($this->auth->owner_id, $path),
				'$dragdroptext' => t('Drop files here to immediately upload'),
				'$notify' => ['notify', t('Show in your contacts shared folder'), 0, '', [t('No'), t('Yes')]]
			));
	}

	/**
	 * This method takes a path/name of an asset and turns it into url
	 * suiteable for http access.
	 *
	 * @param string $assetName
	 * @return string
	 */
	protected function getAssetUrl($assetName) {
		return z_root() . '/cloud/?sabreAction=asset&assetName=' . urlencode($assetName);
	}

	/**
	 * @brief Return the hash of an attachment.
	 *
	 * Given the owner, the parent folder and and attach name get the attachment
	 * hash.
	 *
	 * @param int $owner
	 *  The owner_id
	 * @param string $parentHash
	 *  The parent's folder hash
	 * @param string $attachName
	 *  The name of the attachment
	 * @return string
	 */
	protected function findAttachHash($owner, $parentHash, $attachName) {
		$r = q("SELECT hash FROM attach WHERE uid = %d AND folder = '%s' AND filename = '%s' ORDER BY edited DESC LIMIT 1",
			intval($owner),
			dbesc($parentHash),
			dbesc($attachName)
		);
		$hash = '';
		if ($r) {
			foreach ($r as $rr) {
				$hash = $rr['hash'];
			}
		}

		return $hash;
	}

	/**
	 * @brief Returns an attachment's id for a given hash.
	 *
	 * This id is used to access the attachment in filestorage/
	 *
	 * @param string $attachHash
	 *  The hash of an attachment
	 * @return string
	 */
	 
	protected function findAttachIdByHash($attachHash) {
		$r = q("SELECT id FROM attach WHERE hash = '%s' ORDER BY edited DESC LIMIT 1",
			dbesc($attachHash)
		);
		$id = EMPTY_STR;
		if ($r) {
			$id = $r[0]['id'];
		}
		return $id;
	}
}
