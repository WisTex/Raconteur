<?php

namespace Zotlabs\Storage;

use App;
use Sabre\DAV;
use Zotlabs\Lib\Libsync;
use Zotlabs\Daemon\Run;


require_once('include/photos.php');

/**
 * @brief RedDirectory class.
 *
 * A class that represents a directory.
 *
 * @extends \\Sabre\\DAV\\Node
 * @implements \\Sabre\\DAV\\ICollection
 * @implements \\Sabre\\DAV\\IQuota
 *
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class Directory extends DAV\Node implements DAV\ICollection, DAV\IQuota, DAV\IMoveTarget {

	/**
	 * @brief The path inside /cloud
	 *
	 * @var string $red_path
	 */
	private $red_path;
	private $folder_hash;
	/**
	 * @brief The full path as seen in the browser.
	 * /cloud + $red_path
	 * @todo I think this is not used anywhere, we always strip '/cloud' and only use it in debug
	 * @var string $ext_path
	 */
	private $ext_path;
	private $root_dir = '';
	private $auth;
	/**
	 * @brief The real path on the filesystem.
	 * The actual path in store/ with the hashed names.
	 *
	 * @var string $os_path
	 */
	private $os_path = '';

	/**
	 * @brief Sets up the directory node, expects a full path.
	 *
	 * @param string $ext_path a full path
	 * @param BasicAuth &$auth_plugin
	 */
	public function __construct($ext_path, &$auth_plugin) {
		//		$ext_path = urldecode($ext_path);
		logger('directory ' . $ext_path, LOGGER_DATA);
		$this->ext_path = $ext_path;
		// remove "/cloud" from the beginning of the path
		$modulename = App::$module;
		$this->red_path = ((strpos($ext_path, '/' . $modulename) === 0) ? substr($ext_path, strlen($modulename) + 1) : $ext_path);
		if (! $this->red_path) {
			$this->red_path = '/';
		}
		$this->auth = $auth_plugin;
		$this->folder_hash = '';
		$this->getDir();

		if($this->auth->browser) {
			$this->auth->browser->set_writeable();
		}
	}

	private function log() {
		logger('ext_path ' . $this->ext_path, LOGGER_DATA);
		logger('os_path  ' . $this->os_path, LOGGER_DATA);
		logger('red_path ' . $this->red_path, LOGGER_DATA);
	}

	/**
	 * @brief Returns an array with all the child nodes.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @return array \\Sabre\\DAV\\INode[]
	 */
	public function getChildren() {
		logger('children for ' . $this->ext_path, LOGGER_DATA);
		$this->log();

		if (get_config('system', 'block_public') && (! $this->auth->channel_id) && (! $this->auth->observer)) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if (($this->auth->owner_id) && (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'view_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$contents = $this->CollectionData($this->red_path, $this->auth);
		return $contents;
	}

	/**
	 * @brief Returns a child by name.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @throw "\Sabre\DAV\Exception\NotFound"
	 * @param string $name
	 */
	public function getChild($name) {
		logger($name, LOGGER_DATA);

		if (get_config('system', 'block_public') && (! $this->auth->channel_id) && (! $this->auth->observer)) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if (($this->auth->owner_id) && (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'view_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$modulename = App::$module;
		if ($this->red_path === '/' && $name === $modulename) {
			return new Directory('/' . $modulename, $this->auth);
		}

		$x = $this->FileData($this->ext_path . '/' . $name, $this->auth);
		if ($x) {
			return $x;
		}

		throw new DAV\Exception\NotFound('The file with name: ' . $name . ' could not be found.');
	}

	/**
	 * @brief Returns the name of the directory.
	 *
	 * @return string
	 */
	public function getName() {
		return (basename($this->red_path));
	}

	/**
	 * @brief Renames the directory.
	 *
	 * @todo handle duplicate directory name
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @param string $name The new name of the directory.
	 * @return void
	 */
	public function setName($name) {
		logger('old name ' . basename($this->red_path) . ' -> ' . $name, LOGGER_DATA);

		if ((! $name) || (! $this->auth->owner_id)) {
			logger('permission denied ' . $name);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage')) {
			logger('permission denied '. $name);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		list($parent_path, ) = \Sabre\Uri\split($this->red_path);
		$new_path = $parent_path . '/' . $name;

		$r = q("UPDATE attach SET filename = '%s' WHERE hash = '%s' AND uid = %d",
			dbesc($name),
			dbesc($this->folder_hash),
			intval($this->auth->owner_id)
		);

		$x = attach_syspaths($this->auth->owner_id,$this->folder_hash);

		$y = q("update attach set display_path = '%s' where hash = '%s' and uid = %d",
			dbesc($x['path']),
			dbesc($this->folder_hash),
			intval($this->auth->owner_id)
		);

		$ch = channelx_by_n($this->auth->owner_id);
		if ($ch) {
			$sync = attach_export_data($ch, $this->folder_hash);
			if ($sync) {
				Libsync::build_sync_packet($ch['channel_id'], array('file' => array($sync)));
			}
		}

		$this->red_path = $new_path;
	}

	/**
	 * @brief Creates a new file in the directory.
	 *
	 * Data will either be supplied as a stream resource, or in certain cases
	 * as a string. Keep in mind that you may have to support either.
	 *
	 * After successful creation of the file, you may choose to return the ETag
	 * of the new file here.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @param string $name Name of the file
	 * @param resource|string $data Initial payload
	 * @return null|string ETag
	 */
	public function createFile($name, $data = null) {
		logger('create file in directory ' . $name, LOGGER_DEBUG);

		if (! $this->auth->owner_id) {
			logger('permission denied ' . $name);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage')) {
			logger('permission denied ' . $name);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$mimetype = z_mime_content_type($name);

		$channel = channelx_by_n($this->auth->owner_id);

		if (! $channel) {
			logger('no channel');
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$filesize = 0;
		$hash = new_uuid();

		$f = 'store/' . $this->auth->owner_nick . '/' . (($this->os_path) ? $this->os_path . '/' : '') . $hash;

		$direct = null;

		if ($this->folder_hash) {
			$r = q("select * from attach where hash = '%s' and is_dir = 1 and uid = %d limit 1",
				dbesc($this->folder_hash),
				intval($channel['channel_id'])
			);
			if ($r) {
				$direct = array_shift($r);
			}
		}

		if (($direct) && (($direct['allow_cid']) || ($direct['allow_gid']) || ($direct['deny_cid']) || ($direct['deny_gid']))) {
			$allow_cid = $direct['allow_cid'];
			$allow_gid = $direct['allow_gid'];
			$deny_cid  = $direct['deny_cid'];
			$deny_gid  = $direct['deny_gid'];
		}
		else {
			$allow_cid = $channel['channel_allow_cid'];
			$allow_gid = $channel['channel_allow_gid'];
			$deny_cid = $channel['channel_deny_cid'];
			$deny_gid = $channel['channel_deny_gid'];
		}

		$created = $edited = datetime_convert();

		$r = q("INSERT INTO attach ( aid, uid, hash, creator, filename, folder, os_storage, filetype, filesize, revision, is_photo, content, created, edited, os_path, display_path, allow_cid, allow_gid, deny_cid, deny_gid )
			VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ",
			intval($channel['channel_account_id']),
			intval($channel['channel_id']),
			dbesc($hash),
			dbesc($this->auth->observer),
			dbesc($name),
			dbesc($this->folder_hash),
			intval(1),
			dbesc($mimetype),
			intval($filesize),
			intval(0),
			intval(0),
			dbesc($f),
			dbesc($created),
			dbesc($edited),
			'', 
			'', 
			dbesc($allow_cid),
			dbesc($allow_gid),
			dbesc($deny_cid),
			dbesc($deny_gid)
		);

		// fetch the actual storage paths

		$xpath = attach_syspaths($this->auth->owner_id, $hash);

		if (is_resource($data)) {
			$fp = fopen($f,'wb');
			if ($fp) {
				pipe_streams($data,$fp);
				fclose($fp);
			}
			$size = filesize($f);
		}
		else {
			$size = file_put_contents($f, $data);
		}
		
		// delete attach entry if file_put_contents() failed
		if ($size === false) {
			logger('file_put_contents() failed to ' . $f);
			attach_delete($channel['channel_id'], $hash);
			return;
		}

		$is_photo = 0;
		$gis = @getimagesize($f);
		logger('getimagesize: ' . print_r($gis,true), LOGGER_DATA);
		if (($gis) && supported_imagetype($gis[2])) {
			$is_photo = 1;
		}

		// If we know it's a photo, over-ride the type in case the source system could not determine what it was

		if ($is_photo) {
			q("update attach set filetype = '%s' where hash = '%s' and uid = %d",
				dbesc($gis['mime']),
				dbesc($hash),
				intval($channel['channel_id'])
			);
		}

		// updates entry with path and filesize
		$d = q("UPDATE attach SET filesize = '%s', os_path = '%s', display_path = '%s', is_photo = %d WHERE hash = '%s' AND uid = %d",
			dbesc($size),
			dbesc($xpath['os_path']),
			dbesc($xpath['path']),
			intval($is_photo),
			dbesc($hash),
			intval($channel['channel_id'])
		);

		// update the parent folder's lastmodified timestamp
		$e = q("UPDATE attach SET edited = '%s' WHERE hash = '%s' AND uid = %d",
			dbesc($edited),
			dbesc($this->folder_hash),
			intval($channel['channel_id'])
		);

		$maxfilesize = get_config('system', 'maxfilesize');
		if (($maxfilesize) && ($size > $maxfilesize)) {
			logger('system maxfilesize exceeded. Deleting uploaded file.');
			attach_delete($channel['channel_id'], $hash);
			return;
		}

		// check against service class quota
		$limit = engr_units_to_bytes(service_class_fetch($channel['channel_id'], 'attach_upload_limit'));
		if ($limit !== false) {
			$z = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d ",
				intval($channel['channel_account_id'])
			);
			if (($z) && ($z[0]['total'] + $size > $limit)) {
				logger('service class limit exceeded for ' . $channel['channel_name'] . ' total usage is ' . $z[0]['total'] . ' limit is ' . userReadableSize($limit));
				attach_delete($channel['channel_id'], $hash);
				return;
			}
		}

		if ($is_photo) {
			$album = '';
			if ($this->folder_hash) {
				$f1 = q("select filename, display_path from attach WHERE hash = '%s' AND uid = %d",
					dbesc($this->folder_hash),
					intval($channel['channel_id'])
				);
				if ($f1) {
					$album = (($f1[0]['display_path']) ? $f1[0]['display_path'] : $f1[0]['filename']);
				}
			}

			$args = [
				'resource_id'  => $hash,
				'album'        => $album,
				'folder'       => $this->folder_hash,
				'os_syspath'   => $f,
				'os_path'      => $xpath['os_path'],
				'display_path' => $xpath['path'],
				'filename'     => $name,
				'getimagesize' => $gis,
				'directory'    => $direct
			];
			$p = photo_upload($channel, App::get_observer(), $args);
		}
		
		Run::Summon([ 'Thumbnail' , $hash ]);

		$sync = attach_export_data($channel, $hash);

		if ($sync) {
			Libsync::build_sync_packet($channel['channel_id'], array('file' => array($sync)));
		}
	}

	/**
	 * @brief Creates a new subdirectory.
	 *
	 * @param string $name the directory to create
	 * @return void
	 */
	public function createDirectory($name) {
		logger('create directory ' . $name, LOGGER_DEBUG);

		if ((! $this->auth->owner_id) || (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$channel = channelx_by_n($this->auth->owner_id);

		if ($channel) {

			// When initiated from DAV, set the 'force' flag on attach_mkdir(). This will cause the operation to report success even if the 
			// folder already exists. 

			require_once('include/attach.php');
			$result = attach_mkdir($channel, $this->auth->observer, array('filename' => $name, 'folder' => $this->folder_hash, 'force' => true));

			if ($result['success']) {
				$sync = attach_export_data($channel,$result['data']['hash']);
				logger('createDirectory: attach_export_data returns $sync:' . print_r($sync, true), LOGGER_DEBUG);

				if ($sync) {
					Libsync::build_sync_packet($channel['channel_id'], array('file' => array($sync)));
				}
			}
			else {
				logger('error ' . print_r($result, true), LOGGER_DEBUG);
			}
		}
	}

	/**
	 * @brief delete directory
	 */
	public function delete() {
		logger('delete file ' . basename($this->red_path), LOGGER_DEBUG);

		if ((! $this->auth->owner_id) || (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if ($this->auth->owner_id !== $this->auth->channel_id) {
			if (($this->auth->observer !== $this->data['creator']) || intval($this->data['is_dir'])) {
				throw new DAV\Exception\Forbidden('Permission denied.');
			}
		}

		attach_delete($this->auth->owner_id, $this->folder_hash);

		$channel = channelx_by_n($this->auth->owner_id);
		if ($channel) {
			$sync = attach_export_data($channel, $this->folder_hash, true);
			if ($sync) {
				Libsync::build_sync_packet($channel['channel_id'], array('file' => array($sync)));
			}
		}
	}


	/**
	 * @brief Checks if a child exists.
	 *
	 * @param string $name
	 *  The name to check if it exists.
	 * @return boolean
	 */
	public function childExists($name) {
		// On /cloud we show a list of available channels.
		// @todo what happens if no channels are available?
		$modulename = App::$module;
		if ($this->red_path === '/' && $name === $modulename) {
			//logger('We are at ' $modulename . ' show a channel list', LOGGER_DEBUG);
			return true;
		}

		$x = $this->FileData($this->ext_path . '/' . $name, $this->auth, true);
		//logger('FileData returns: ' . print_r($x, true), LOGGER_DATA);
		if ($x) {
			return true;
		}
		return false;
	}


	public function moveInto($targetName,$sourcePath, DAV\INode $sourceNode) {

		if (! $this->auth->owner_id) {
			return false;
		}

		if(! ($sourceNode->data && $sourceNode->data['hash'])) {
			return false;
		}

		return attach_move($this->auth->owner_id, $sourceNode->data['hash'], $this->folder_hash);
	}


	/**
	 * @todo add description of what this function does.
	 *
	 * @throw "\Sabre\DAV\Exception\NotFound"
	 * @return void
	 */
	function getDir() {

		logger('GetDir: ' . $this->ext_path, LOGGER_DEBUG);
		$this->auth->log();
		$modulename = App::$module;

		$file = $this->ext_path;

		$x = strpos($file, '/' . $modulename);
		if ($x === 0) {
			$file = substr($file, strlen($modulename) + 1);
		}

		if ((! $file) || ($file === '/')) {
			return;
		}

		$file = trim($file, '/');
		$path_arr = explode('/', $file);

		if (! $path_arr)
			return;

		logger('paths: ' . print_r($path_arr, true), LOGGER_DATA);

		$channel_name = $path_arr[0];

		$channel = channelx_by_nick($channel_name);

		if (! $channel) {
			throw new DAV\Exception\NotFound('The file with name: ' . $channel_name . ' could not be found.');
		}

		$channel_id = $channel['channel_id'];
		$this->auth->owner_id = $channel_id;
		$this->auth->owner_nick = $channel_name;

		$path = '/' . $channel_name;
		$folder = '';
		$os_path = '';

		for ($x = 1; $x < count($path_arr); $x++) {
			$r = q("select id, hash, filename, flags, is_dir from attach where folder = '%s' and filename = '%s' and uid = %d and is_dir != 0",
				dbesc($folder),
				dbesc($path_arr[$x]),
				intval($channel_id)
			);
			if ($r && intval($r[0]['is_dir'])) {
				$folder = $r[0]['hash'];
				if (strlen($os_path)) {
					$os_path .= '/';
				}
				$os_path .= $folder;
				$path = $path . '/' . $r[0]['filename'];
			}
		}
		$this->folder_hash = $folder;
		$this->os_path = $os_path;
	}

	/**
	 * @brief Returns the last modification time for the directory, as a UNIX
	 * timestamp.
	 *
	 * It looks for the last edited file in the folder. If it is an empty folder
	 * it returns the lastmodified time of the folder itself, to prevent zero
	 * timestamps.
	 *
	 * @return int last modification time in UNIX timestamp
	 */
	public function getLastModified() {
		$r = q("SELECT edited FROM attach WHERE folder = '%s' AND uid = %d ORDER BY edited DESC LIMIT 1",
			dbesc($this->folder_hash),
			intval($this->auth->owner_id)
		);
		if (! $r) {
			$r = q("SELECT edited FROM attach WHERE hash = '%s' AND uid = %d LIMIT 1",
				dbesc($this->folder_hash),
				intval($this->auth->owner_id)
			);
			if (! $r)
				return '';
		}
		return datetime_convert('UTC', 'UTC', $r[0]['edited'], 'U');
	}


	/**
	 * @brief Array with all Directory and File DAV\\Node items for the given path.
 	 *
	 * @param string $file path to a directory
	 * @param \Zotlabs\Storage\BasicAuth &$auth
	 * @returns null|array \\Sabre\\DAV\\INode[]
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @throw "\Sabre\DAV\Exception\NotFound"
	 */
	function CollectionData($file, &$auth) {
		$ret = [];

		$x = strpos($file, '/cloud');
		if ($x === 0) {
			$file = substr($file, 6);
		}

		// return a list of channel if we are not inside a channel
		if ((! $file) || ($file === '/')) {
			return $this->ChannelList($auth);
		}

		$file = trim($file, '/');
		$path_arr = explode('/', $file);

		if (! $path_arr) {
			return null;
		}

		$channel_name = $path_arr[0];

		$channel = channelx_by_nick($channel_name);

		if (! $channel) {
			return null;
		}

		$channel_id = $channel['channel_id'];
		$perms = permissions_sql($channel_id);

		$auth->owner_id = $channel_id;

		$path = '/' . $channel_name;

		$folder = '';
		$errors = false;
		$permission_error = false;

		for ($x = 1; $x < count($path_arr); $x++) {
			$r = q("SELECT id, hash, filename, flags, is_dir FROM attach WHERE folder = '%s' AND filename = '%s' AND uid = %d AND is_dir != 0 $perms LIMIT 1",
				dbesc($folder),
				dbesc($path_arr[$x]),
				intval($channel_id)
			);
			if (! $r) {
				// path wasn't found. Try without permissions to see if it was the result of permissions.
				$errors = true;
				$r = q("select id, hash, filename, flags, is_dir from attach where folder = '%s' and filename = '%s' and uid = %d and is_dir != 0 limit 1",
					dbesc($folder),
					basename($path_arr[$x]),
					intval($channel_id)
				);
				if ($r) {
					$permission_error = true;
				}
				break;
			}

			if ($r && intval($r[0]['is_dir'])) {
				$folder = $r[0]['hash'];
				$path = $path . '/' . $r[0]['filename'];
			}
		}

		if ($errors) {
			if ($permission_error) {
				throw new DAV\Exception\Forbidden('Permission denied.');
			}
			else {
				throw new DAV\Exception\NotFound('A component of the requested file path could not be found.');
			}
		}

		// This should no longer be needed since we just returned errors for paths not found
		if ($path !== '/' . $file) {
			logger("Path mismatch: $path !== /$file");
			return NULL;
		}

		$prefix = '';

		if(! array_key_exists('cloud_sort',$_SESSION))
			$_SESSION['cloud_sort'] = 'name';

		switch($_SESSION['cloud_sort']) {
			case 'size': 
				$suffix = ' order by is_dir desc, filesize asc ';
				break;
			// The following provides inconsistent results for directories because we re-calculate the date for directories based on the most recent change
			case 'date':
				$suffix = ' order by is_dir desc, edited asc ';
				break;
			case 'name':
			default:
				$suffix = ' order by is_dir desc, filename asc ';
				break;
		}

		$r = q("select $prefix id, uid, hash, filename, filetype, filesize, revision, folder, flags, is_dir, created, edited from attach where folder = '%s' and uid = %d $perms $suffix",
			dbesc($folder),
			intval($channel_id)
		);

		foreach ($r as $rr) {
			if (App::$module === 'cloud' && (strpos($rr['filename'],'.') === 0) && (! get_pconfig($channel_id,'system','show_dot_files')) ) {
				continue;
			}

			// @FIXME I don't think we use revisions currently in attach structures.
			// In case we see any in the wild provide a unique filename. This 
			// name may or may not be accessible

			if ($rr['revision']) {
				$rr['filename'] .= '-' . $rr['revision'];
			}

			// logger('filename: ' . $rr['filename'], LOGGER_DEBUG);
			if (intval($rr['is_dir'])) {
				$ret[] = new Directory($path . '/' . $rr['filename'], $auth);
			}
			else {
				$ret[] = new File($path . '/' . $rr['filename'], $rr, $auth);
			}
		}

		return $ret;
	}


	/**
	 * @brief Returns an array with viewable channels.
	 *
	 * Get a list of Directory objects with all the channels where the visitor
	 * has <b>view_storage</b> perms.
	 *
	 *
	 * @param BasicAuth &$auth
	 * @return array Directory[]
 	 */
	function ChannelList(&$auth) {
		$ret = [];

		$disabled = intval(get_config('system','cloud_disable_siteroot',true));
		
		$r = q("SELECT channel_id, channel_address, profile.publish FROM channel left join profile on profile.uid = channel.channel_id WHERE channel_removed = 0 AND channel_system = 0 AND (channel_pageflags & %d) = 0 and profile.is_default = 1",
			intval(PAGE_HIDDEN)
		);
		if ($r) {
			foreach ($r as $rr) {
				if ((perm_is_allowed($rr['channel_id'], $auth->observer, 'view_storage') && $rr['publish']) || $rr['channel_id'] == $this->auth->channel_id) {
					logger('found channel: /cloud/' . $rr['channel_address'], LOGGER_DATA);
					if ($disabled) {
						$conn = q("select abook_id from abook where abook_channel = %d and abook_xchan = '%s' and abook_pending = 0",
							intval($rr['channel_id']),
							dbesc($auth->observer)
						);
						if (! $conn) {
							continue;
						}
					}

					$ret[] = new Directory($rr['channel_address'], $auth);
				}
			}
		}
		return $ret;
	}


	/**
	 * @brief
	 *
	 * @param string $file
	 *  path to file or directory
	 * @param BasicAuth &$auth
	 * @param boolean $test (optional) enable test mode
	 * @return File|Directory|boolean|null
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 */
	function FileData($file, &$auth, $test = false) {
		logger($file . (($test) ? ' (test mode) ' : ''), LOGGER_DATA);

		$x = strpos($file, '/cloud');
		if ($x === 0) {
			$file = substr($file, 6);
		}
		else {
			$x = strpos($file, '/dav');
			if($x === 0)
				$file = substr($file, 4);
		}

		if ((! $file) || ($file === '/')) {
			return new Directory('/', $auth);
		}

		$file = trim($file, '/');

		$path_arr = explode('/', $file);

		if (! $path_arr)
			return null;

		$channel_name = $path_arr[0];

		$r = q("select channel_id from channel where channel_address = '%s' limit 1",
			dbesc($channel_name)
		);

		if (! $r)
			return null;

		$channel_id = $r[0]['channel_id'];

		$path = '/' . $channel_name;

		$auth->owner_id = $channel_id;

		$permission_error = false;

		$folder = '';

		require_once('include/security.php');
		$perms = permissions_sql($channel_id);

		$errors = false;

		for ($x = 1; $x < count($path_arr); $x++) {
			$r = q("select id, hash, filename, flags, is_dir from attach where folder = '%s' and filename = '%s' and uid = %d and is_dir != 0 $perms",
				dbesc($folder),
				dbesc($path_arr[$x]),
				intval($channel_id)
			);

			if ($r && intval($r[0]['is_dir'])) {
				$folder = $r[0]['hash'];
				$path = $path . '/' . $r[0]['filename'];
			}
			if (! $r) {
				$r = q("select id, uid, hash, filename, filetype, filesize, revision, folder, flags, is_dir, os_storage, created, edited from attach
					where folder = '%s' and filename = '%s' and uid = %d $perms order by filename limit 1",
					dbesc($folder),
					dbesc(basename($file)),
					intval($channel_id)
				);
			}
			if (! $r) {
				$errors = true;
				$r = q("select id, uid, hash, filename, filetype, filesize, revision, folder, flags, is_dir, os_storage, created, edited from attach
					where folder = '%s' and filename = '%s' and uid = %d order by filename limit 1",
					dbesc($folder),
					dbesc(basename($file)),
					intval($channel_id)
				);
				if ($r)
					$permission_error = true;
			}
		}

		if ($path === '/' . $file) {
			if ($test)
				return true;
			// final component was a directory.
			return new Directory($file, $auth);
		}

		if ($errors) {
			logger('not found ' . $file);
			if ($test)
				return false;
			if ($permission_error) {
				logger('permission error ' . $file);
				throw new DAV\Exception\Forbidden('Permission denied.');
			}
			return;
		}

		if ($r) {
			if ($test)
				return true;

			if (intval($r[0]['is_dir'])) {
				return new Directory($path . '/' . $r[0]['filename'], $auth);
			}
			else {
				return new File($path . '/' . $r[0]['filename'], $r[0], $auth);
			}
		}
		return false;
	}

	public function getQuotaInfo() {

		/**
		 * Returns the quota information
		 *
		 * This method MUST return an array with 2 values, the first being the total used space,
		 * the second the available space (in bytes)
		 */

		$used  = 0;
		$limit = 0;
		$free  = 0;
		
		if ($this->auth->owner_id) {
			$channel = channelx_by_n($this->auth->owner_id);
			if($channel) {
				$r = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d",
					intval($channel['channel_account_id'])
				);
				$used  = (($r) ? (float) $r[0]['total'] : 0);
				$limit = (float) engr_units_to_bytes(service_class_fetch($this->auth->owner_id, 'attach_upload_limit'));
				if($limit) {
					// Don't let the result go negative
					$free = (($limit > $used) ? $limit - $used : 0);
				}
			}
		}

		if(! $limit) {
			$free = disk_free_space('store');
			$used = disk_total_space('store') - $free;
		}

		// prevent integer overflow on 32-bit systems

		if($used > (float) PHP_INT_MAX)
			$used = PHP_INT_MAX;
		if($free > (float) PHP_INT_MAX)
			$free = PHP_INT_MAX;

		return [ (int) $used, (int) $free ];

	}

}
