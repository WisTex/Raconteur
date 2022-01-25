<?php

namespace Zotlabs\Storage;

use App;
use Sabre\DAV;
use Zotlabs\Lib\Libsync;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\Channel;
use Zotlabs\Lib\ServiceClass;

require_once('include/photos.php');

/**
 * @brief This class represents a file in DAV.
 *
 * It provides all functions to work with files in the project cloud through DAV protocol.
 *
 * @extends \Sabre\\DAV\\Node
 * @implements \Sabre\\DAV\\IFile
 *
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */

class File extends DAV\Node implements DAV\IFile {

	/**
	 * The file from attach table.
	 *
	 * @var array $data
	 *  * data
	 *  * flags
	 *  * filename (string)
	 *  * filetype (string)
	 */

	public $data;

	/**
	 * @see \\Sabre\\DAV\\Auth\\Backend\\BackendInterface
	 * @var \Zotlabs\\Storage\\BasicAuth $auth
	 */

	private $auth;

	/**
	 * @var string $name
	 */

	private $name;

	/**
	 * Sets up the node, expects a full path name.
	 *
	 * @param string $name
	 * @param array $data from attach table
	 * @param &$auth
	 */

	public function __construct($name, $data, &$auth) {
		$this->name = $name;
		$this->data = $data;
		$this->auth = $auth;
	}

	/**
	 * @brief Returns the name of the file.
	 *
	 * @return string
	 */

	public function getName() {
		return basename($this->name);
	}

	/**
	 * @brief Renames the file.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 * @param string $newName The new name of the file.
	 * @return void
	 */

	public function setName($newName) {
		logger('old name ' . basename($this->name) . ' -> ' . $newName, LOGGER_DATA);

		if ((! $newName) || (! $this->auth->owner_id) || (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage'))) {
			logger('permission denied '. $newName);
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$newName = str_replace('/', '%2F', $newName);

		$r = q("UPDATE attach SET filename = '%s' WHERE hash = '%s' AND id = %d",
			dbesc($newName),
			dbesc($this->data['hash']),
			intval($this->data['id'])
		);

		$x = attach_syspaths($this->auth->owner_id,$this->data['hash']);

		$y = q("update attach set display_path = '%s where hash = '%s' and uid = %d",
			dbesc($x['path']),
			dbesc($this->data['hash']),
			intval($this->auth->owner_id)
		);

		if ($this->data->is_photo) {
			$r = q("update photo set filename = '%s', display_path = '%s' where resource_id = '%s' and uid = %d",
				dbesc($newName),
				dbesc($x['path']),
				dbesc($this->data['hash']),
				intval($this->auth->owner_id)
			);
		}

		$ch = Channel::from_id($this->auth->owner_id);
		if ($ch) {
			$sync = attach_export_data($ch,$this->data['hash']);
			if ($sync) {
				Libsync::build_sync_packet($ch['channel_id'], [ 'file' => [ $sync ] ]);
			}
		}
	}

	/**
	 * @brief Updates the data of the file.
	 *
	 * @param resource $data
	 * @return void
	 */

	public function put($data) {
		logger('put file: ' . basename($this->name), LOGGER_DEBUG);
		$size = 0;


		if ((! $this->auth->owner_id) || (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage'))) {
			logger('permission denied for put operation');
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$channel = Channel::from_id($this->auth->owner_id);

		if (! $channel) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		$is_photo = false;
		$album = '';
		$os_path = '';


		// This hidden config allows you to protect your dav contents from cryptolockers by preventing over-write
		// and delete from a networked operating system. In this case you are only allowed to over-write the file
		// if it is empty. Some DAV clients create the file and then store the contents so these would be allowed. 

		if (get_pconfig($this->auth->owner_id,'system','os_delete_prohibit') && App::$module == 'dav') {
			$r = q("select filesize from attach where hash = '%s' and uid = %d limit 1",
				dbesc($this->data['hash']),
				intval($channel['channel_id'])
			);
			if ($r && intval($r[0]['filesize'])) { 	
				throw new DAV\Exception\Forbidden('Permission denied.');
			}
		}

		$r = q("SELECT flags, folder, os_storage, os_path, display_path, filename, is_photo FROM attach WHERE hash = '%s' AND uid = %d LIMIT 1",
			dbesc($this->data['hash']),
			intval($channel['channel_id'])
		);
		if ($r) {

			$os_path = $r[0]['os_path'];
			$display_path = $r[0]['display_path'];
			$filename = $r[0]['filename'];
			$folder_hash = $r[0]['folder'];

			if (intval($r[0]['os_storage'])) {
				$d = q("select folder, content from attach where hash = '%s' and uid = %d limit 1",
					dbesc($this->data['hash']),
					intval($channel['channel_id'])
				);
				if ($d) {
					if ($d[0]['folder']) {
						$f1 = q("select * from attach where is_dir = 1 and hash = '%s' and uid = %d limit 1",
							dbesc($d[0]['folder']),
							intval($channel['channel_id'])
						);
						if ($f1) {
							$album = $f1[0]['filename'];
							$direct = $f1[0];
						}
					}
					$fname = dbunescbin($d[0]['content']);
					if (strpos($fname,'store/') === false) {
						$f = 'store/' . $this->auth->owner_nick . '/' . $fname ;
					}
					else {
						$f = $fname;
					}
					
					if (is_resource($data)) {
						$fp = fopen($f,'wb');
						if ($fp) {
							pipe_streams($data,$fp);
							fclose($fp);
						}
					}
					else {
						file_put_contents($f, $data);
					}

					$size = @filesize($f);

					logger('filename: ' . $f . ' size: ' . $size, LOGGER_DEBUG);
				}
				$gis = @getimagesize($f);
				logger('getimagesize: ' . print_r($gis,true), LOGGER_DATA);
				if ($gis && supported_imagetype($gis[2])) {
					$is_photo = 1;
				}

				// If we know it's a photo, over-ride the type in case the source system could not determine what it was

				if ($is_photo) {
					q("update attach set filetype = '%s' where hash = '%s' and uid = %d",
						dbesc($gis['mime']),
						dbesc($this->data['hash']),
						intval($this->data['uid'])
					);
				}

			}
			else {
				// this shouldn't happen any more
				$r = q("UPDATE attach SET content = '%s' WHERE hash = '%s' AND uid = %d",
					dbescbin(stream_get_contents($data)),
					dbesc($this->data['hash']),
					intval($this->data['uid'])
				);
				$r = q("SELECT length(content) AS fsize FROM attach WHERE hash = '%s' AND uid = %d LIMIT 1",
					dbesc($this->data['hash']),
					intval($this->data['uid'])
				);
				if ($r) {
					$size = $r[0]['fsize'];
				}
			}
		}

		// returns now()
		$edited = datetime_convert();

		$d = q("UPDATE attach SET filesize = '%s', is_photo = %d, edited = '%s' WHERE hash = '%s' AND uid = %d",
			dbesc($size),
			intval($is_photo),
			dbesc($edited),
			dbesc($this->data['hash']),
			intval($channel['channel_id'])
		);

		if ($is_photo) {
			$args = [
				'resource_id'  => $this->data['hash'],
				'album'        => $album,
				'folder'       => $folder_hash,
				'os_syspath'   => $f,
				'os_path'      => $os_path,
				'display_path' => $display_path,
				'filename'     => $filename,
				'getimagesize' => $gis,
				'directory'    => $direct
			];
			$p = photo_upload($channel, App::get_observer(), $args);
			logger('photo_upload: ' . print_r($p,true), LOGGER_DATA);
		}

		// update the folder's lastmodified timestamp
		$e = q("UPDATE attach SET edited = '%s' WHERE hash = '%s' AND uid = %d",
			dbesc($edited),
			dbesc($r[0]['folder']),
			intval($channel['channel_id'])
		);

		// @todo do we really want to remove the whole file if an update fails
		// because of maxfilesize or quota?
		// There is an Exception "InsufficientStorage" or "PaymentRequired" for
		// our service class from SabreDAV we could use.

		$maxfilesize = get_config('system', 'maxfilesize');
		if (($maxfilesize) && ($size > $maxfilesize)) {
			attach_delete($channel['channel_id'], $this->data['hash']);
			return;
		}

		$limit = engr_units_to_bytes(ServiceClass::fetch($channel['channel_id'], 'attach_upload_limit'));
		if ($limit !== false) {
			$x = q("select sum(filesize) as total from attach where aid = %d ",
				intval($channel['channel_account_id'])
			);
			if (($x) && ($x[0]['total'] + $size > $limit)) {
				logger('service class limit exceeded for ' . $channel['channel_name'] . ' total usage is ' . $x[0]['total'] . ' limit is ' . userReadableSize($limit));
				attach_delete($channel['channel_id'], $this->data['hash']);
				return;
			}
		}

		Run::Summon([ 'Thumbnail' , $this->data['hash'] ]);

		$sync = attach_export_data($channel,$this->data['hash']);

		if ($sync) {
			Libsync::build_sync_packet($channel['channel_id'],array('file' => array($sync)));
		}
	}

	/**
	 * @brief Returns the raw data.
	 *
	 * @return string || resource
	 */

	public function get() {
		logger('get file ' . basename($this->name), LOGGER_DEBUG);
		logger('os_path: ' . $this->os_path, LOGGER_DATA);

		$r = q("SELECT content, flags, os_storage, filename, filetype FROM attach WHERE hash = '%s' AND uid = %d LIMIT 1",
			dbesc($this->data['hash']),
			intval($this->data['uid'])
		);
		if ($r) {
			// @todo this should be a global definition
			$unsafe_types = array('text/html', 'text/css', 'application/javascript', 'image/svg+xml');

			if (in_array($r[0]['filetype'], $unsafe_types) && (!Channel::codeallowed($this->data['uid']))) {
				header('Content-Disposition: attachment; filename="' . $r[0]['filename'] . '"');
				header('Content-type: ' . $r[0]['filetype']);
			}

			if (intval($r[0]['os_storage'])) {
				$x = dbunescbin($r[0]['content']);
				if (strpos($x,'store') === false) {
					$f = 'store/' . $this->auth->owner_nick . '/' . (($this->os_path) ? $this->os_path . '/' : '') . $x;
				}
				else {
					$f = $x;
				}
				return @fopen($f, 'rb');
			}
			return dbunescbin($r[0]['content']);
		}
	}

	/**
	 * @brief Returns the ETag for a file.
	 *
	 * An ETag is a unique identifier representing the current version of the file.
	 * If the file changes, the ETag MUST change.
	 * The ETag is an arbitrary string, but MUST be surrounded by double-quotes.
	 *
	 * Return null if the ETag can not effectively be determined.
	 *
	 * @return null|string
	 */
	public function getETag() {
		$ret = null;
		if ($this->data['hash']) {
			$ret = '"' . $this->data['hash'] . '"';
		}
		return $ret;
	}

	/**
	 * @brief Returns the mime-type for a file.
	 *
	 * If null is returned, we'll assume application/octet-stream
	 *
	 * @return mixed
	 */

	public function getContentType() {
		return $this->data['filetype'];
	}

	/**
	 * @brief Returns the size of the node, in bytes.
	 *
	 * @return int
	 *  filesize in bytes
	 */
	public function getSize() {
		return intval($this->data['filesize']);
	}

	/**
	 * @brief Returns the last modification time for the file, as a unix
	 *        timestamp.
	 *
	 * @return int last modification time in UNIX timestamp
	 */

	public function getLastModified() {
		return datetime_convert('UTC', 'UTC', $this->data['edited'], 'U');
	}

	/**
	 * @brief Delete the file.
	 *
	 * This method checks the permissions and then calls attach_delete() function
	 * to actually remove the file.
	 *
	 * @throw "\Sabre\DAV\Exception\Forbidden"
	 */
	public function delete() {
		logger('delete file ' . basename($this->name), LOGGER_DEBUG);

		if ((! $this->auth->owner_id) || (! perm_is_allowed($this->auth->owner_id, $this->auth->observer, 'write_storage'))) {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		if ($this->auth->owner_id !== $this->auth->channel_id) {
			if (($this->auth->observer !== $this->data['creator']) || intval($this->data['is_dir'])) {
				throw new DAV\Exception\Forbidden('Permission denied.');
			}
		}

		// This is a subtle solution to crypto-lockers which can wreak havoc on network resources when
		// invoked on a dav-mounted filesystem. By setting system.os_delete_prohibit, one can remove files
		// via the web interface but from their operating system the filesystem is treated as read-only. 
		
		if (get_pconfig($this->auth->owner_id,'system','os_delete_prohibit') && App::$module == 'dav') {
			throw new DAV\Exception\Forbidden('Permission denied.');
		}

		attach_delete($this->auth->owner_id, $this->data['hash']);

		$channel = Channel::from_id($this->auth->owner_id);
		if ($channel) {
			$sync = attach_export_data($channel, $this->data['hash'], true);
			if ($sync) {
				Libsync::build_sync_packet($channel['channel_id'], [ 'file' => [ $sync ] ]);
			}
		}
	}
}
