<?php

/**
 * @file include/attach.php
 *
 * @brief File/attach API with the potential for revision control.
 *
 * @todo Also an 'append' option to the storage function might be a useful addition.
 */

use Code\Lib\Libsync;
use Code\Lib\AccessList;
use Code\Daemon\Run;
use Code\Lib\Channel;
use Code\Lib\ServiceClass;
use Code\Extend\Hook;
use Code\Storage\Stdio;

require_once('include/permissions.php');
require_once('include/security.php');


/**
 * @brief Guess the mimetype from file ending.
 *
 * This function takes a file name and guess the mimetype from the
 * filename extension.
 *
 * @param string $filename a string filename
 * @return string The mimetype according to a file ending.
 */
function z_mime_content_type($filename)
{

    $mime_types = [

    'txt'  => 'text/plain',
    'htm'  => 'text/html',
    'html' => 'text/html',
    'css'  => 'text/css',
    'md'   => 'text/markdown',
    'bb'   => 'text/bbcode',
    'mc'   => 'text/x-multicode',
    'abc'  => 'text/vnd.abc',
    'csv'  => 'text/csv',
    'js'   => 'application/javascript',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'swf'  => 'application/x-shockwave-flash',
    'flv'  => 'video/x-flv',
    'epub' => 'application/epub+zip',
    'c'    => 'text/plain',
    'h'    => 'text/plain',
    'sh'   => 'text/plain',
    'py'   => 'text/plain',
    'php'  => 'text/plain',
    'rb'   => 'text/plain',
    'pdl'  => 'text/plain',
    'ics'  => 'text/calendar',

    // images

    'png'  => 'image/png',
    'jpe'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'jpg'  => 'image/jpeg',
    'gif'  => 'image/gif',
    'bmp'  => 'image/bmp',
    'webp' => 'image/webp',
    'ico'  => 'image/vnd.microsoft.icon',
    'tiff' => 'image/tiff',
    'tif'  => 'image/tiff',
    'svg'  => 'image/svg+xml',
    'svgz' => 'image/svg+xml',

    // archives
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    'exe' => 'application/x-msdownload',
    'msi' => 'application/x-msdownload',
    'cab' => 'application/vnd.ms-cab-compressed',

    // audio/video
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'qt'   => 'video/quicktime',
    'mov'  => 'video/quicktime',
    'ogg'  => 'audio/ogg',
    'ogv'  => 'video/ogg',
    'ogx'  => 'application/ogg',
    'flac' => 'audio/flac',
    'opus' => 'audio/ogg',
    'webm' => 'video/webm',
    'mp4'  => 'video/mp4',
    'mkv'  => 'video/x-matroska',

    // adobe
    'pdf' => 'application/pdf',
    'psd' => 'image/vnd.adobe.photoshop',
    'ai'  => 'application/postscript',
    'eps' => 'application/postscript',
    'ps'  => 'application/postscript',

    // ms office
    'doc' => 'application/msword',
    'rtf' => 'application/rtf',
    'xls' => 'application/vnd.ms-excel',
    'ppt' => 'application/vnd.ms-powerpoint',

    // open office
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    'odp' => 'application/vnd.oasis.opendocument.presentation',
    'odg' => 'application/vnd.oasis.opendocument.graphics',
    'odc' => 'application/vnd.oasis.opendocument.chart',
    'odf' => 'application/vnd.oasis.opendocument.formula',
    'odi' => 'application/vnd.oasis.opendocument.image',
    'odm' => 'application/vnd.oasis.opendocument.text-master',
    'odb' => 'application/vnd.oasis.opendocument.database',
    'ott' => 'application/vnd.oasis.opendocument.text-template',
    'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
    'otp' => 'application/vnd.oasis.opendocument.presentation-template',
    'otg' => 'application/vnd.oasis.opendocument.graphics-template',
    'otc' => 'application/vnd.oasis.opendocument.chart-template',
    'otf' => 'application/vnd.oasis.opendocument.formula-template',
    'oti' => 'application/vnd.oasis.opendocument.image-template',
    'oth' => 'application/vnd.oasis.opendocument.text-web'
    ];

    $last_dot = strrpos($filename, '.');
    if ($last_dot !== false) {
        $ext = strtolower(substr($filename, $last_dot + 1));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
    }

    return 'application/octet-stream';
}

/**
 * @brief Count files/attachments.
 *
 * @param int $channel_id
 * @param string $observer
 * @param string $hash (optional)
 * @param string $filename (optional)
 * @param string $filetype (optional)
 * @return array
 *  * \e boolean \b success
 *  * \e int|boolean \b results amount of found results, or false
 *  * \e string \b message with error messages if any
 */
function attach_count_files($channel_id, $observer, $hash = '', $filename = '', $filetype = '')
{

    $ret = [ 'success' => false ];

    if (! perm_is_allowed($channel_id, $observer, 'read_storage')) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    require_once('include/security.php');
    $sql_extra = permissions_sql($channel_id);

    if ($hash) {
        $sql_extra .= protect_sprintf(" and hash = '" . dbesc($hash) . "' ");
    }
    if ($filename) {
        $sql_extra .= protect_sprintf(" and filename like '@" . dbesc($filename) . "@' ");
    }
    if ($filetype) {
        $sql_extra .= protect_sprintf(" and filetype like '@" . dbesc($filetype) . "@' ");
    }
    $r = q(
        "select id, uid, folder from attach where uid = %d $sql_extra",
        intval($channel_id)
    );

    $ret['success'] = is_array($r);
    $ret['results'] = ((is_array($r)) ? count($r) : false);

    return $ret;
}

/**
 * @brief Returns a list of files/attachments.
 *
 * @param $channel_id
 * @param $observer
 * @param $hash
 * @param $filename
 * @param $filetype
 * @param $orderby
 * @param $start
 * @param $entries
 * @return array
 *  * \e boolean \b success
 *  * \e array|boolean \b results array with results, or false
 *  * \e string \b message with error messages if any
 */
function attach_list_files($channel_id, $observer, $hash = '', $filename = '', $filetype = '', $orderby = 'created desc', $start = 0, $entries = 0, $since = '', $until = '')
{

    $ret = [ 'success' => false ];

    if (! perm_is_allowed($channel_id, $observer, 'view_storage')) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }


    require_once('include/security.php');
    $sql_extra = permissions_sql($channel_id);

    if ($hash) {
        $sql_extra .= protect_sprintf(" and hash = '" . dbesc($hash) . "' ");
    }
    if ($filename) {
        $sql_extra .= protect_sprintf(" and filename like '%" . dbesc($filename) . "%' ");
    }
    if ($filetype) {
        $sql_extra .= protect_sprintf(" and filetype like '%" . dbesc($filetype) . "%' ");
    }
    if ($entries) {
        $limit = " limit " . intval($start) . ", " . intval($entries) . " ";
    }
    if (! $since) {
        $since = NULL_DATE;
    }
    if (! $until) {
        $until = datetime_convert();
    }

    $sql_extra .= " and created >= '" . dbesc($since) . "' and created <= '" . dbesc($until) . "' ";

    // Retrieve all columns except 'content'

    $r = q(
        "select id, aid, uid, hash, filename, filetype, filesize, revision, folder, os_path, display_path, os_storage, is_dir, is_photo, flags, created, edited, allow_cid, allow_gid, deny_cid, deny_gid from attach where uid = %d $sql_extra ORDER BY $orderby $limit",
        intval($channel_id)
    );

    $ret['success'] = is_array($r);
    $ret['results'] = ((is_array($r)) ? $r : []);

    return $ret;
}

/**
 * @brief Find an attachment by hash and revision.
 *
 * Returns the entire attach structure including data.
 *
 * This could exhaust memory so most useful only when immediately sending the data.
 *
 * @param string $hash
 * @param string $observer_hash
 * @param int $rev (optional) Revision default 0
 * @return array
 */
function attach_by_hash($hash, $observer_hash, $rev = 0)
{

    $ret = [ 'success' => false ];

    // Check for existence, which will also provide us the owner uid

    $sql_extra = '';
    if ($rev == (-1)) {
        $sql_extra = " order by revision desc ";
    } elseif ($rev) {
        $sql_extra = " and revision = " . intval($rev) . " ";
    }

    $r = q(
        "SELECT uid FROM attach WHERE hash = '%s' $sql_extra LIMIT 1",
        dbesc($hash)
    );
    if (! $r) {
        $ret['message'] = t('Item was not found.');
        return $ret;
    }

    if (! attach_can_view($r[0]['uid'], $observer_hash, $hash)) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    // We've already checked for existence and permissions

    $r = q(
        "SELECT * FROM attach WHERE hash = '%s' and uid = %d $sql_extra LIMIT 1",
        dbesc($hash),
        intval($r[0]['uid'])
    );

    if (! $r) {
        $ret['message'] = t('Unknown error.');
        return $ret;
    }

    $r[0]['content'] = dbunescbin($r[0]['content']);

    $ret['success'] = true;
    $ret['data'] = array_shift($r);

    return $ret;
}


function attach_can_view($uid, $ob_hash, $resource, $token = EMPTY_STR)
{

    $sql_extra = permissions_sql($uid, $ob_hash, '', $token);
    $hash = $resource;

    if (! $token) {
        if (! perm_is_allowed($uid, $ob_hash, 'view_storage')) {
            return false;
        }
    }

    $r = q(
        "select folder from attach where hash = '%s' and uid = %d $sql_extra",
        dbesc($hash),
        intval($uid)
    );

    if (! $r) {
        return false;
    }

    // don't perform recursive folder check when using OCAP. Only when using ACL access.
    // For OCAP if the token is valid they can see the thing.

    if ($token) {
        return true;
    }

    return attach_can_view_folder($uid, $ob_hash, $r[0]['folder'], $token);
}



function attach_can_view_folder($uid, $ob_hash, $folder_hash, $token = EMPTY_STR)
{

    $sql_extra = permissions_sql($uid, $ob_hash, '', $token);
    $hash = $folder_hash;
    $result = false;

    if ((! $folder_hash) && (! $token)) {
        return perm_is_allowed($uid, $ob_hash, 'view_storage');
    }


    do {
        $r = q(
            "select folder from attach where hash = '%s' and uid = %d $sql_extra",
            dbesc($hash),
            intval($uid)
        );
        if (! $r) {
            return false;
        }
        $hash = $r[0]['folder'];
    } while ($hash);

    return true;
}


/**
 * @brief Find an attachment by hash and revision.
 *
 * Returns the entire attach structure excluding data.
 *
 * @see attach_by_hash()
 * @param string $hash
 * @param string $observer_hash
 * @param int $rev (optional) revision default 0
 * @return array
 *  with everything except data
 *  * \e boolean \b success boolean true or false
 *  * \e string \b message (optional) only when success is false
 *  * \e array \b data array of attach DB entry without data component
 */
function attach_by_hash_nodata($hash, $observer_hash, $rev = 0)
{

    $ret = [ 'success' => false ];

    // Check for existence, which will also provide us the owner uid

    $sql_extra = '';
    if ($rev == (-1)) {
        $sql_extra = " order by revision desc ";
    } elseif ($rev) {
        $sql_extra = " and revision = " . intval($rev) . " ";
    }

    $r = q(
        "SELECT uid FROM attach WHERE hash = '%s' $sql_extra LIMIT 1",
        dbesc($hash)
    );
    if (! $r) {
        $ret['message'] = t('Item was not found.');
        return $ret;
    }

    if (! perm_is_allowed($r[0]['uid'], $observer_hash, 'view_storage')) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    $sql_extra = permissions_sql($r[0]['uid'], $observer_hash);

    // Now we'll see if we can access the attachment

    $a = q(
        "select id, aid, uid, hash, creator, filename, filetype, filesize, revision, folder, os_storage, is_photo, os_path, display_path, is_dir, flags, created, edited, allow_cid, allow_gid, deny_cid, deny_gid from attach where uid = %d and hash = '%s' $sql_extra limit 1",
        intval($r[0]['uid']),
        dbesc($hash)
    );

    if (! $a) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    if ($a[0]['folder']) {
        $x = attach_can_view_folder($a[0]['uid'], $observer_hash, $a[0]['folder']);
        if (! $x) {
            $ret['message'] = t('Permission denied.');
            return $ret;
        }
    }

    $ret['success'] = true;
    $ret['data'] = array_shift($a);

    return $ret;
}

/**
 * @brief Stores an attachment from a POST file upload.
 *
 * This function stores an attachment. It can be a new one, a replacement or a
 * new revision depending on value set in \e $options.
 *
 * @note Requires an input field \e userfile and does not accept multiple files
 * in one request.
 *
 * @note A lot going on in this function, and some of it is old cruft and some is new cruft
 * and the entire thing probably needs to be refactored. It started out just storing
 * files, before we had DAV. It was made extensible to do extra stuff like edit an
 * existing file or optionally store a separate revision using $options to choose between different
 * storage models. Along the way we moved from
 * DB data storage to file system storage.
 * Then DAV came along and used different upload methods depending on whether the
 * file was stored as a DAV directory object or updated as a file object. One of these
 * is essentially an update and the other is basically an upload, but doesn't use the traditional PHP
 * upload workflow.
 * Then came hubzilla and we tried to merge photo functionality with the file storage. Most of
 * that integration occurs within this function.
 * This required overlap with the old photo_upload stuff and photo albums were
 * completely different concepts from directories which needed to be reconciled somehow.
 * The old revision stuff is kind of orphaned currently. There's new revision stuff for photos
 * which attaches (2) etc. onto the name, but doesn't integrate with the attach table revisioning.
 * That's where it sits currently. I repeat it needs to be refactored, and this note is here
 * for future explorers and those who may be doing that work to understand where it came
 * from and got to be the monstrosity of tangled unrelated code that it currently is.
 *
 * @param array $channel channel array of owner
 * @param string $observer_hash hash of current observer
 * @param string $options (optional) one of update, replace, revision
 * @param array $arr (optional) associative array
 * @return void|array
 */
function attach_store($channel, $observer_hash, $options = '', $arr = null)
{

    require_once('include/photos.php');

    /**
     * @hooks photo_upload_begin
     *   Called when attempting to upload a photo.
     */
    Hook::call('photo_upload_begin', $arr);

    $ret = ['success' => false];
    $channel_id = $channel['channel_id'];
    $sql_options = '';
    $source = (($arr) ? $arr['source'] : '');
    $album = (($arr) ? $arr['album'] : '');
    $newalbum = (($arr) ? $arr['newalbum'] : '');
    $hash = (($arr && $arr['hash']) ? $arr['hash'] : null);
    $upload_path = (($arr && $arr['directory']) ? $arr['directory'] : '');
    $visible = (($arr && $arr['visible']) ? $arr['visible'] : '');
    $notify = (($arr && $arr['notify']) ? $arr['notify'] : '');
    $flags = (($arr && array_key_exists('flags', $arr)) ? intval($arr['flags']) : 0);
    $observer = [];

    $dosync = ((array_key_exists('nosync', $arr) && $arr['nosync']) ? 0 : 1);

    if ($observer_hash) {
        $observer = xchan_match([ 'xchan_hash' => $observer_hash ]);
    }

    logger('arr: ' . print_r($arr, true), LOGGER_DATA);

    if (! perm_is_allowed($channel_id, $observer_hash, 'write_storage')) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    $str_group_allow   = perms2str($arr['group_allow']);
    $str_contact_allow = perms2str($arr['contact_allow']);
    $str_group_deny    = perms2str($arr['group_deny']);
    $str_contact_deny  = perms2str($arr['contact_deny']);


    // The 'update' option sets db values without uploading a new attachment
    // 'replace' replaces the existing uploaded data
    // 'revision' creates a new revision with new upload data
    // Default is to upload a new file

    // revise or update must provide $arr['hash'] of the thing to revise/update

    // By default remove $src when finished

    $remove_when_processed = true;
    $import_replace = false;

    if ($options === 'import') {
        $src      = $arr['src'];
        $filename = $arr['filename'];
        $filesize = @filesize($src);

        $hash     = $arr['resource_id'];

        if (array_key_exists('hash', $arr)) {
            $hash = $arr['hash'];
        }
        if (array_key_exists('type', $arr)) {
            $type = $arr['type'];
        }

        if ($arr['preserve_original']) {
            $remove_when_processed = false;
        }

        if ($arr['replace']) {
            $import_replace = true;
        }

        // if importing a directory, just do it now and go home - we're done.

        if (array_key_exists('is_dir', $arr) && intval($arr['is_dir'])) {
            $x = attach_mkdir($channel, $observer_hash, $arr);
            if ($x['message']) {
                logger('import_directory: ' . $x['message']);
            }

            return;
        }
    } elseif ($options !== 'update') {
        $f = [
                'src' => '',
                'filename' => '',
                'filesize' => 0,
                'type' => ''
        ];

        /**
         * @hooks photo_upload_file
         *   Called to generate alternate filenames for an upload.
         *   * \e string \b src - return value, default empty
         *   * \e string \b filename - return value, default empty
         *   * \e number \b filesize - return value, default 0
         *   * \e string \b type - return value, default empty
         */
        Hook::call('photo_upload_file', $f);
        /**
         * @hooks attach_upload_file
         *   Called when uploading a file.
         *   * \e string \b src - return value, default empty
         *   * \e string \b filename - return value, default empty
         *   * \e number \b filesize - return value, default 0
         *   * \e string \b type - return value, default empty
         */
        Hook::call('attach_upload_file', $f);

        if (x($f, 'src') && x($f, 'filesize')) {
            $src      = $f['src'];
            $filename = $f['filename'];
            $filesize = $f['filesize'];
            $type     = $f['type'];
        } else {
            if (! x($_FILES, 'userfile')) {
                logger('No source file');
                $ret['message'] = t('No source file.');
                return $ret;
            }

            $src      = $_FILES['userfile']['tmp_name'];
            $filename = basename($_FILES['userfile']['name']);
            $filesize = intval($_FILES['userfile']['size']);
        }
    }

    // AndStatus sends jpegs with a non-standard mimetype
    if ($type === 'image/jpg') {
        $type = 'image/jpeg';
    }

    $existing_size = 0;

    if ($options === 'replace') {
        $x = q(
            "select id, hash, filesize from attach where id = %d and uid = %d limit 1",
            intval($arr['id']),
            intval($channel_id)
        );
        if (! $x) {
            $ret['message'] = t('Cannot locate file to replace');
            return $ret;
        }
        $existing_id = $x[0]['id'];
        $existing_size = intval($x[0]['filesize']);
        $hash = $x[0]['hash'];
    }

    if ($options === 'revise' || $options === 'update') {
        $sql_options = " order by revision desc ";
        if ($options === 'update' &&  $arr && array_key_exists('revision', $arr)) {
            $sql_options = " and revision = " . intval($arr['revision']) . " ";
        }

        $x = q(
            "select id, aid, uid, filename, filetype, filesize, hash, revision, folder, os_storage, is_photo, os_path, display_path, flags, created, edited, allow_cid, allow_gid, deny_cid, deny_gid from attach where hash = '%s' and uid = %d $sql_options limit 1",
            dbesc($arr['hash']),
            intval($channel_id)
        );
        if (! $x) {
            logger('update file source not found');
            $ret['message'] = t('Cannot locate file to revise/update');
            return $ret;
        }
        $hash = $x[0]['hash'];
    }

    $def_extension = '';
    $is_photo = 0;
    $gis = false;

    if ($src) {
        $gis = @getimagesize($src);
        logger('getimagesize: ' . print_r($gis, true), LOGGER_DATA);
    }

    if (($gis) && in_array($gis[2], [ IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ])) {
        $is_photo = 1;
        if ($gis[2] === IMAGETYPE_GIF) {
            $def_extension =  '.gif';
        }
        if ($gis[2] === IMAGETYPE_JPEG) {
            $def_extension =  '.jpg';
        }
        if ($gis[2] === IMAGETYPE_PNG) {
            $def_extension =  '.png';
        }
        if ($gis[2] === IMAGETYPE_WEBP) {
            $def_extension =  '.webp';
        }
    }

    // If we know it's a photo, over-ride the type in case the source system could not determine what it was

    if ($is_photo) {
        $type = $gis['mime'];
    }

    $pathname = '';

    // If we were called from the Photos module there is a slightly different mechanism
    // for setting the parent path than if we were called from the Files (cloud) module.

    if ($source === 'photos') {
        if ($newalbum) {
            $pathname = filepath_macro($newalbum);
        } elseif (array_key_exists('folder', $arr)) {
            $x = q(
                "select filename from attach where hash = '%s' and uid = %d limit 1",
                dbesc($arr['folder']),
                intval($channel['channel_id'])
            );
            if ($x) {
                $pathname = $x[0]['filename'];
            }
        } else {
            $pathname = filepath_macro($album);
        }
    }
    elseif ($album && !$newalbum) {
        // We can land here from the profile_photo and cover_photo modules.
        // Just use the album provided.
        $pathname = filepath_macro($album);
    }

    if (! $pathname) {
        $pathname = filepath_macro($upload_path);
    }

    $darr = ['pathname' => $pathname];

    // if we need to create a directory, use the channel default permissions.

    $darr['allow_cid'] = $channel['allow_cid'];
    $darr['allow_gid'] = $channel['allow_gid'];
    $darr['deny_cid']  = $channel['deny_cid'];
    $darr['deny_gid']  = $channel['deny_gid'];


    $direct = null;

    if ($pathname) {
        $x = attach_mkdirp($channel, $observer_hash, $darr);
        $folder_hash = (($x['success']) ? $x['data']['hash'] : '');
        $direct = (($x['success']) ? $x['data'] : null);
        if ((! $str_contact_allow) && (! $str_group_allow) && (! $str_contact_deny) && (! $str_group_deny)) {
            $str_contact_allow = $x['data']['allow_cid'];
            $str_group_allow = $x['data']['allow_gid'];
            $str_contact_deny = $x['data']['deny_cid'];
            $str_group_deny = $x['data']['deny_gid'];
        }
    } else {
        $folder_hash = ((($arr) && array_key_exists('folder', $arr)) ? $arr['folder'] : '');
    }

    if ((! $options) || ($options === 'import')) {
        // A freshly uploaded file. Check for duplicate and resolve with the channel's overwrite settings.

        $existing = q(
            "select filename, id, hash, filesize from attach where uid = %d and filename = '%s' and folder = '%s' ",
            intval($channel_id),
            dbesc($filename),
            dbesc($folder_hash)
        );
        if ($existing) {
            $overwrite = $import_replace || get_pconfig($channel_id, 'system', 'overwrite_dup_files');
            if (($overwrite) || ($options === 'import')) {
                if (! array_key_exists('edited', $arr)) {
                    $arr['edited'] = datetime_convert();
                }
                $options = 'replace';
                $existing_id = $existing[0]['id'];
                $existing_size = intval($existing[0]['filesize']);
                $hash = $existing[0]['hash'];
            } else {
                if (str_contains($filename, '.')) {
                    $basename = substr($filename, 0, strrpos($filename, '.'));
                    $ext = substr($filename, strrpos($filename, '.'));
                } else {
                    $basename = $filename;
                    $ext = $def_extension;
                }

                $r = q(
                    "select filename from attach where uid = %d and ( filename = '%s' OR filename like '%s' ) and folder = '%s' ",
                    intval($channel_id),
                    dbesc($basename . $ext),
                    dbesc($basename . '(%)' . $ext),
                    dbesc($folder_hash)
                );

                if ($r) {
                    $x = 1;

                    do {
                        $found = false;
                        foreach ($r as $rr) {
                            if ($rr['filename'] === $basename . '(' . $x . ')' . $ext) {
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            $x++;
                        }
                    } while ($found);
                    $filename = $basename . '(' . $x . ')' . $ext;
                } else {
                    $filename = $basename . $ext;
                }
            }
        }
    }

    if (! $hash) {
        $hash = new_uuid();
    }

    // Check storage limits
    if ($options !== 'update') {
        $maxfilesize = get_config('system', 'maxfilesize');

        if (($maxfilesize) && ($filesize > $maxfilesize)) {
            logger('quota_exceeded');
            $ret['message'] = sprintf(t('File exceeds size limit of %d'), $maxfilesize);
            if ($remove_when_processed) {
                @unlink($src);
            }

            /**
             * @hooks photo_upload_end
             *   Called when a photo upload has been processed.
             */
            Hook::call('photo_upload_end', $ret);

            return $ret;
        }

        $limit = engr_units_to_bytes(ServiceClass::fetch($channel_id, 'attach_upload_limit'));

        if ($limit !== false) {
            $r = q(
                "select sum(filesize) as total from attach where aid = %d ",
                intval($channel['channel_account_id'])
            );
            if (($r) &&  (($r[0]['total'] + $filesize) > ($limit - $existing_size))) {
                logger('service_class limit exceeded');
                $ret['message'] = ServiceClass::upgrade_message(true) . sprintf(t("You have reached your limit of %1$.0f Mbytes attachment storage."), $limit / 1024000);
                if ($remove_when_processed) {
                    @unlink($src);
                }

                /**
                 * @hooks photo_upload_end
                 *   Called when a photo upload has been processed.
                 */
                Hook::call('photo_upload_end', $ret);

                return $ret;
            }
        }
        $mimetype = ((isset($type) && $type) ? $type : z_mime_content_type($filename));
    }

    $os_basepath = 'store/' . $channel['channel_address'] . '/' ;
    $os_relpath = '';

    if ($folder_hash) {
        $curr = find_folder_hash_by_attach_hash($channel_id, $folder_hash, true);
        if ($curr) {
            $os_relpath .= $curr . '/';
        }
        $os_relpath .= $folder_hash . '/';
    }

    $os_relpath  .= $hash;
    $os_relpath   = ltrim($os_relpath, '/');

    $os_path      = $os_relpath;
    $display_path = ltrim($pathname . '/' . $filename, '/');

    if ($src) {
        Stdio::fcopy($src, $os_basepath . $os_relpath);
    }

    if (array_key_exists('created', $arr)) {
        $created = $arr['created'];
    } else {
        $created = datetime_convert();
    }

    if (array_key_exists('edited', $arr)) {
        $edited = $arr['edited'];
    } else {
        $edited = $created;
    }

    if ($options === 'replace') {
        $r = q(
            "update attach set filename = '%s', filetype = '%s', folder = '%s', filesize = %d, os_storage = %d, is_photo = %d, content = '%s', edited = '%s', os_path = '%s', display_path = '%s' where id = %d and uid = %d",
            dbesc($filename),
            dbesc($mimetype),
            dbesc($folder_hash),
            intval($filesize),
            intval(1),
            intval($is_photo),
            dbescbin($os_basepath . $os_relpath),
            dbesc($created),
            dbesc($os_path),
            dbesc($display_path),
            intval($existing_id),
            intval($channel_id)
        );
    } elseif ($options === 'revise') {
        $r = q(
            "insert into attach ( aid, uid, hash, creator, filename, filetype, folder, filesize, revision, os_storage,
                is_photo, content, created, edited, os_path, display_path, allow_cid, allow_gid, deny_cid, deny_gid )
                VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', %d, %d, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ",
            intval($x[0]['aid']),
            intval($channel_id),
            dbesc($x[0]['hash']),
            dbesc($observer_hash),
            dbesc($filename),
            dbesc($mimetype),
            dbesc($folder_hash),
            intval($filesize),
            intval($x[0]['revision'] + 1),
            intval(1),
            intval($is_photo),
            dbescbin($os_basepath . $os_relpath),
            dbesc($created),
            dbesc($created),
            dbesc($os_path),
            dbesc($display_path),
            dbesc($x[0]['allow_cid']),
            dbesc($x[0]['allow_gid']),
            dbesc($x[0]['deny_cid']),
            dbesc($x[0]['deny_gid'])
        );
    } elseif ($options === 'update') {
        $r = q(
            "update attach set filename = '%s', filetype = '%s', folder = '%s', edited = '%s', os_storage = %d, is_photo = %d, os_path = '%s',
            display_path = '%s', allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid  = '%s' where id = %d and uid = %d",
            dbesc((array_key_exists('filename', $arr))  ? $arr['filename']  : $x[0]['filename']),
            dbesc((array_key_exists('filetype', $arr))  ? $arr['filetype']  : $x[0]['filetype']),
            dbesc(($folder_hash) ?: $x[0]['folder']),
            dbesc($created),
            dbesc((array_key_exists('os_storage', $arr))  ? $arr['os_storage']  : $x[0]['os_storage']),
            dbesc((array_key_exists('is_photo', $arr))  ? $arr['is_photo']  : $x[0]['is_photo']),
            dbesc((array_key_exists('os_path', $arr))   ? $arr['os_path']   : $x[0]['os_path']),
            dbesc((array_key_exists('display_path', $arr))   ? $arr['display_path']   : $x[0]['display_path']),
            dbesc((array_key_exists('allow_cid', $arr)) ? $arr['allow_cid'] : $x[0]['allow_cid']),
            dbesc((array_key_exists('allow_gid', $arr)) ? $arr['allow_gid'] : $x[0]['allow_gid']),
            dbesc((array_key_exists('deny_cid', $arr))  ? $arr['deny_cid']  : $x[0]['deny_cid']),
            dbesc((array_key_exists('deny_gid', $arr))  ? $arr['deny_gid']  : $x[0]['deny_gid']),
            intval($x[0]['id']),
            intval($x[0]['uid'])
        );
    } else {
        $r = q(
            "INSERT INTO attach ( aid, uid, hash, creator, filename, filetype, folder, filesize, revision, os_storage, is_photo, flags, content, created, edited, os_path, display_path, allow_cid, allow_gid,deny_cid, deny_gid )
                VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', %d, %d, %d, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ",
            intval($channel['channel_account_id']),
            intval($channel_id),
            dbesc($hash),
            dbesc(get_observer_hash()),
            dbesc($filename),
            dbesc($mimetype),
            dbesc($folder_hash),
            intval($filesize),
            intval(0),
            intval(1),
            intval($is_photo),
            intval($flags),
            dbescbin($os_basepath . $os_relpath),
            dbesc($created),
            dbesc($created),
            dbesc($os_path),
            dbesc($display_path),
            dbesc(($arr && array_key_exists('allow_cid', $arr)) ? $arr['allow_cid'] : $str_contact_allow),
            dbesc(($arr && array_key_exists('allow_gid', $arr)) ? $arr['allow_gid'] : $str_group_allow),
            dbesc(($arr && array_key_exists('deny_cid', $arr))  ? $arr['deny_cid']  : $str_contact_deny),
            dbesc(($arr && array_key_exists('deny_gid', $arr))  ? $arr['deny_gid']  : $str_group_deny)
        );
    }

    if ($is_photo) {
        // Call the photos library to generate all the standard thumbnails.
        // This may fail if the image format isn't supported by the image library *or* memory is exhausted during the attempt.
        // That's why this is called at the end of the upload when all the important work to store as a normal file has been accomplished.

        $args = ['source' => $source, 'visible' => $visible, 'resource_id' => $hash, 'album' => $pathname, 'folder' => $folder_hash, 'os_syspath' => $os_basepath . $os_relpath, 'os_path' => $os_path, 'display_path' => $display_path, 'filename' => $filename, 'getimagesize' => $gis, 'directory' => $direct, 'options' => $options];
        if ($arr['contact_allow']) {
            $args['contact_allow'] = $arr['contact_allow'];
        }
        if ($arr['group_allow']) {
            $args['group_allow'] = $arr['group_allow'];
        }
        if ($arr['contact_deny']) {
            $args['contact_deny'] = $arr['contact_deny'];
        }
        if ($arr['group_deny']) {
            $args['group_deny'] = $arr['group_deny'];
        }
        if (array_key_exists('allow_cid', $arr)) {
            $args['allow_cid'] = $arr['allow_cid'];
        }
        if (array_key_exists('allow_gid', $arr)) {
            $args['allow_gid'] = $arr['allow_gid'];
        }
        if (array_key_exists('deny_cid', $arr)) {
            $args['deny_cid'] = $arr['deny_cid'];
        }
        if (array_key_exists('deny_gid', $arr)) {
            $args['deny_gid'] = $arr['deny_gid'];
        }

        $args['created'] = $created;
        $args['edited'] = $edited;
        if ($arr['item']) {
            $args['item'] = $arr['item'];
        }

        if ($arr['description']) {
            $args['description'] = $arr['description'];
        }

        if ($arr['title']) {
            $args['title'] = $arr['title'];
        }

        if ($arr['body']) {
            $args['body'] = $arr['body'];
        }


        $args['deliver'] = $dosync;

        $p = photo_upload($channel, $observer, $args);
        if ($p['success']) {
            $ret['body'] = $p['body'];
        }
    }

    if (($options !== 'update') && ($remove_when_processed)) {
        @unlink($src);
    }

    if (! $r) {
        $ret['message'] = t('File upload failed. Possible system limit or action terminated.');

        /**
         * @hooks photo_upload_end
         *   Called when a photo upload has been processed.
         */
        Hook::call('photo_upload_end', $ret);

        return $ret;
    }

    // Update the folder timestamp @todo recurse to the storage root folder

    if ($folder_hash) {
        q(
            "UPDATE attach set edited = '%s' where hash = '%s' and uid = %d and is_dir = 1",
            dbesc($edited),
            dbesc($folder_hash),
            intval($channel_id)
        );
    }

    // Caution: This re-uses $sql_options set further above

    $r = q(
        "select * from attach where uid = %d and hash = '%s' $sql_options limit 1",
        intval($channel_id),
        dbesc($hash)
    );

    if (! $r) {
        $ret['message'] = t('Stored file could not be verified. Upload failed.');

        /**
         * @hooks photo_upload_end
         *   Called when a photo upload has been processed.
         */
        Hook::call('photo_upload_end', $ret);

        return $ret;
    }

    $ret['success'] = true;
    $ret['data'] = $r[0];
    if (! $is_photo) {
        /**
         * @hooks photo_upload_end
         *   Called when a photo upload has been processed.
         *   This would've been called already with a success result in photos_upload() if it was a photo.
         */
        Hook::call('photo_upload_end', $ret);
    }

    Run::Summon([ 'Thumbnail' , $hash ]);


    if ($dosync) {
        $sync = attach_export_data($channel, $hash);

        if ($sync) {
            Libsync::build_sync_packet($channel['channel_id'], ['file' => [$sync]]);
        }
    }

    if ($notify) {
        $cloudPath =  z_root() . '/cloud/' . $channel['channel_address'] . '/' . $r['0']['display_path'];
        $object = get_file_activity_object($channel['channel_id'], $r['0']['hash'], $cloudPath);
        file_activity($channel['channel_id'], $object, $r['0']['allow_cid'], $r['0']['allow_gid'], $r['0']['deny_cid'], $r['0']['deny_gid'], 'post', $notify);
    }

    return $ret;
}

/**
 * @brief Read a virtual directory and return contents.
 *
 * Also checking permissions of all parent components.
 *
 * @param int $channel_id
 * @param string $observer_hash hash of current observer
 * @param string $pathname
 * @param string $parent_hash (optional)
 *
 * @return array $ret
 *  * \e boolean \b success boolean true or false
 *  * \e string \b message error message if success is false
 *  * \e array \b data array of attach DB entries without data component
 */
function z_readdir($channel_id, $observer_hash, $pathname, $parent_hash = '')
{

    $ret = [ 'success' => false ];

    if (! perm_is_allowed($channel_id, $observer_hash, 'view_storage')) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    if (strpos($pathname, '/')) {
        $paths = explode('/', $pathname);
        if (count($paths) > 1) {
            $curpath = array_shift($paths);

            $r = q(
                "select hash, id, is_dir from attach where uid = %d and filename = '%s' and is_dir != 0 " . permissions_sql($channel_id, $observer_hash) . " limit 1",
                intval($channel_id),
                dbesc($curpath)
            );
            if (! $r) {
                $ret['message'] = t('Path not available.');
                return $ret;
            }
            // recurse
            return z_readdir($channel_id, $observer_hash, implode('/', $paths), $r[0]['hash']);
        }
    } else {
        $paths = [$pathname];
    }

    $r = q(
        "select id, aid, uid, hash, creator, filename, filetype, filesize, revision, folder, os_path, display_path, is_photo, is_dir, os_storage, flags, created, edited, allow_cid, allow_gid, deny_cid, deny_gid from attach where id = %d and folder = '%s' and filename = '%s' and is_dir != 0 " . permissions_sql($channel_id),
        intval($channel_id),
        dbesc($parent_hash),
        dbesc($paths[0])
    );
    if (! $r) {
        $ret['message'] = t('Path not available.');
        return $ret;
    }
    $ret['success'] = true;
    $ret['data'] = $r;

    return $ret;
}

/**
 * @brief Returns a list with all folders observer is allowed to see.
 *
 * Returns an associative array with all folders where observer has permissions.
 *
 * @param array $channel
 * @param array $observer
 * @param array $sort_key (optional) default display_path
 * @param array $direction (optional) default asc
 *
 * @return bool|array false if no view_storage permission or an array
 *   * \e boolean \b success
 *   * \e array \b albums
 */
function attach_list($channel, $observer, $sort_key = 'display_path', $direction = 'asc')
{

    $channel_id     = $channel['channel_id'];
    $observer_xchan = (($observer) ? $observer['xchan_hash'] : '');

    if (! perm_is_allowed($channel_id, $observer_xchan, 'view_storage')) {
        return false;
    }

    $sql_extra = permissions_sql($channel_id, $observer_xchan);

    $sort_key = dbesc($sort_key);
    $direction = dbesc($direction);

    $r = q(
        "select display_path, hash from attach where is_dir = 1 and uid = %d $sql_extra order by $sort_key $direction",
        intval($channel_id)
    );

    // add a 'root directory' to the results

    array_unshift($r, [ 'display_path' => '', 'hash' => '' ]);
    $str = ids_to_querystr($r, 'hash', true);

    $folders = [];

    if ($str) {
        $x = q(
            "select count( distinct hash ) as total, folder from attach where uid = %d and folder in ( $str ) $sql_extra group by folder ",
            intval($channel_id)
        );
        if ($x) {
            foreach ($r as $rv) {
                foreach ($x as $xv) {
                    if ($xv['folder'] === $rv['hash']) {
                        if ($xv['total'] != 0 && attach_can_view_folder($channel_id, $observer_xchan, $xv['folder'])) {
                            $folders[] = [ 'path' => $rv['display_path'], 'folder' => $xv['folder'],
                                'location' => 'cloud/' . $channel['channel_address'] . '/' . $rv['display_path'],
                                'total' => $xv['total'] ];
                        }
                    }
                }
            }
        }
    }

    // add various encodings to the array, so we can just loop through and pick them out in a template

    $ret = [ 'success' => false ];

    if ($folders) {
        $ret['success'] = true;
        $ret['folders'] = [];
        foreach ($folders as $folder) {
            $entry = [
                'text'      => (($folder['path']) ?: '/'),
                'shorttext' => (($folder['path']) ? ellipsify($folder['path'], 28) : '/'),
                'jstext'    => (($folder['path']) ? addslashes($folder['path']) : '/'),
                'total'     => $folder['total'],
                'url'       => z_root() . '/' . $folder['location'],
                'urlencode' => urlencode($folder['path']),
                'bin2hex'   => $folder['folder']
            ];
            $ret['folders'][] = $entry;
        }
    }

    App::$data['folders'] = $ret;

    return $ret;
}


/**
 * @brief Create directory.
 *
 * @param array $channel channel array of owner
 * @param string $observer_hash hash of current observer
 * @param array $arr parameter array to fulfil request
 * - Required:
 *  * \e string \b filename
 *  * \e string \b folder hash of parent directory, empty string for root directory
 * - Optional:
 *  * \e string \b hash precomputed hash for this node
 *  * \e tring  \b allow_cid
 *  * \e string \b allow_gid
 *  * \e string \b deny_cid
 *  * \e string \b deny_gid
 * @return array
 */
function attach_mkdir($channel, $observer_hash, $arr = null)
{

    $ret = ['success' => false];
    $channel_id = $channel['channel_id'];

    $sql_options = '';

    $os_basepath = 'store/' . $channel['channel_address'];

    logger('basepath: ' . $os_basepath, LOGGER_DEBUG);

    if (! is_dir($os_basepath)) {
        Stdio::mkdir($os_basepath, STORAGE_DEFAULT_PERMISSIONS, true);
    }

    $os_basepath .= '/';

    if (! perm_is_allowed($channel_id, $observer_hash, 'write_storage')) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    if (! $arr['filename']) {
        $ret['message'] = t('Empty pathname');
        return $ret;
    }

    $arr['hash'] = (($arr['hash']) ?: new_uuid());

    // Check for duplicate name.
    // Check both the filename and the hash as we will be making use of both.

    $r = q(
        "select id, hash, is_dir, flags from attach where ( filename = '%s' or hash = '%s' ) and folder = '%s' and uid = %d limit 1",
        dbesc($arr['filename']),
        dbesc($arr['hash']),
        dbesc($arr['folder']),
        intval($channel['channel_id'])
    );
    if ($r) {
        if (array_key_exists('force', $arr) && intval($arr['force']) && (intval($r[0]['is_dir']))) {
            $ret['success'] = true;
            $r = q(
                "select * from attach where id = %d limit 1",
                intval($r[0]['id'])
            );
            if ($r) {
                $ret['data'] = $r[0];
            }
            return $ret;
        }
        $ret['message'] = t('duplicate filename or path');
        return $ret;
    }

    if ($arr['folder']) {
        // Walk the directory tree from parent back to root to make sure the parent is valid and name is unique and we
        // have permission to see this path. This implies the root directory itself is public since we won't have permissions
        // set on the psuedo-directory. We can however set permissions for anything and everything contained within it.

        $lpath = '';
        $lfile = $arr['folder'];
        $dpath = '';

        $sql_options = permissions_sql($channel['channel_id']);


        do {
            $r = q(
                "select filename, hash, flags, is_dir, folder, display_path from attach where uid = %d and hash = '%s' and is_dir = 1
                    $sql_options limit 1",
                intval($channel['channel_id']),
                dbesc($lfile)
            );
            if (! $r) {
                logger('attach_mkdir: hash ' . $lfile . ' not found in ' . $lpath);
                $ret['message'] = t('Path not found.');
                return $ret;
            }

            $dpath = $r[0]['filename'] . (($dpath) ? '/' . $dpath : '');

            if ($lfile) {
                $lpath = $r[0]['hash'] . (($lpath) ? '/' . $lpath : '');
            }

            $lfile = $r[0]['folder'];
        } while (($r[0]['folder']) && intval($r[0]['is_dir']));

        $path = $lpath;
    } else {
        $path = '';
    }

    $created = datetime_convert();

    $os_path = ltrim($path . '/' . $arr['hash'], '/');
    $display_path = ltrim($dpath . '/' . $arr['filename'], '/');


    $r = q(
        "INSERT INTO attach ( aid, uid, hash, creator, filename, filetype, filesize, revision, folder, os_storage, is_dir, content, created, edited, os_path, display_path, allow_cid, allow_gid, deny_cid, deny_gid )
            VALUES ( %d, %d, '%s', '%s', '%s', '%s', %d, %d, '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ",
        intval($channel['channel_account_id']),
        intval($channel_id),
        dbesc($arr['hash']),
        dbesc(get_observer_hash()),
        dbesc($arr['filename']),
        dbesc('multipart/mixed'),
        intval(0),
        intval(0),
        dbesc($arr['folder']),
        intval(1),
        intval(1),
        dbescbin($os_basepath . $os_path),
        dbesc($created),
        dbesc($created),
        dbesc($os_path),
        dbesc($display_path),
        dbesc(($arr && array_key_exists('allow_cid', $arr)) ? $arr['allow_cid'] : $channel['channel_allow_cid']),
        dbesc(($arr && array_key_exists('allow_gid', $arr)) ? $arr['allow_gid'] : $channel['channel_allow_gid']),
        dbesc(($arr && array_key_exists('deny_cid', $arr))  ? $arr['deny_cid']  : $channel['channel_deny_cid']),
        dbesc(($arr && array_key_exists('deny_gid', $arr))  ? $arr['deny_gid']  : $channel['channel_deny_gid'])
    );

    if ($r) {
        if (Stdio::mkdir($os_basepath . $os_path, STORAGE_DEFAULT_PERMISSIONS, true)) {
            $ret['success'] = true;

            // update the parent folder's lastmodified timestamp
            $e = q(
                "UPDATE attach SET edited = '%s' WHERE hash = '%s' AND uid = %d",
                dbesc($created),
                dbesc($arr['folder']),
                intval($channel_id)
            );

            $z = q(
                "select * from attach where hash = '%s' and uid = %d and is_dir = 1 limit 1",
                dbesc($arr['hash']),
                intval($channel_id)
            );
            if ($z) {
                $ret['data'] = $z[0];
            }
        } else {
            logger('attach_mkdir: ' . $os_basepath . $os_path . ' failed.');
            $ret['message'] = t('mkdir failed.');
        }
    } else {
        $ret['message'] = t('database storage failed.');
    }

    return $ret;
}

/**
 * @brief Create directory (recursive).
 *
 * @param array $channel channel array of owner
 * @param string $observer_hash hash of current observer
 * @param array $arr parameter array to fulfil request
 * - Required:
 *  * \e string \b pathname
 *  * \e string \b folder hash of parent directory, empty string for root directory
 * - Optional:
 *  * \e string \b allow_cid
 *  * \e string \b allow_gid
 *  * \e string \b deny_cid
 *  * \e string \b deny_gid
 * @return array
 */
function attach_mkdirp($channel, $observer_hash, $arr = null)
{

    $ret = [ 'success' => false ];
    $channel_id = $channel['channel_id'];

    $sql_options = '';

    $basepath = 'store/' . $channel['channel_address'];

    logger('basepath: ' . $basepath, LOGGER_DEBUG);

    if (! is_dir($basepath)) {
        Stdio::mkdir($basepath, STORAGE_DEFAULT_PERMISSIONS, true);
    }

    if (! perm_is_allowed($channel_id, $observer_hash, 'write_storage')) {
        $ret['message'] = t('Permission denied.');
        return $ret;
    }

    if (! $arr['pathname']) {
        $ret['message'] = t('Empty pathname');
        return $ret;
    }

    $paths = explode('/', $arr['pathname']);
    if (! $paths) {
        $ret['message'] = t('Empty path');
        return $ret;
    }

    $current_parent = '';

    foreach ($paths as $p) {
        if (! $p) {
            continue;
        }

        $arx = [
            'filename' => $p,
            'folder' => $current_parent,
            'force' => 1
        ];

        if (array_key_exists('allow_cid', $arr)) {
            $arx['allow_cid'] = $arr['allow_cid'];
        }
        if (array_key_exists('deny_cid', $arr)) {
            $arx['deny_cid'] = $arr['deny_cid'];
        }
        if (array_key_exists('allow_gid', $arr)) {
            $arx['allow_gid'] = $arr['allow_gid'];
        }
        if (array_key_exists('deny_gid', $arr)) {
            $arx['deny_gid'] = $arr['deny_gid'];
        }

        $x = attach_mkdir($channel, $observer_hash, $arx);
        if ($x['success']) {
            $current_parent = $x['data']['hash'];
        } else {
            $ret['message'] = $x['message'];
            return $ret;
        }
    }
    if (isset($x)) {
        $ret['success'] = true;
        $ret['data'] = $x['data'];
    }

    return $ret;
}



/**
 * @brief Changes permissions of a file.
 *
 * @param int $channel_id The id of the channel
 * @param string $resource
 * @param string $allow_cid
 * @param string $allow_gid
 * @param string $deny_cid
 * @param string $deny_gid
 * @param bool $recurse (optional) default false
 * @param bool $sync (optional) default false
 */
function attach_change_permissions($channel_id, $resource, $allow_cid, $allow_gid, $deny_cid, $deny_gid, $recurse = false, $sync = false)
{

    $channel = Channel::from_id($channel_id);
    if (! $channel) {
        return;
    }

    $r = q(
        "select hash, flags, is_dir, is_photo, allow_cid from attach where hash = '%s' and uid = %d limit 1",
        dbesc($resource),
        intval($channel_id)
    );

    if (! $r) {
        return;
    }

    $private = $allow_cid || $allow_gid || $deny_cid || $deny_gid;

    // preserve any existing tokens that may have been set for this file
    // @fixme - we need a way to unconditionally clear these if desired.

    if ($private) {
        $token_matches = null;
        if (preg_match_all('/\<token:(.*?)\>/', $r[0]['allow_cid'], $token_matches, PREG_SET_ORDER)) {
            foreach ($token_matches as $m) {
                $tok = '<token:' . $m[1] . '>';
                if (!str_contains($allow_cid, $tok)) {
                    $allow_cid .= $tok;
                }
            }
        }
    }

    if (intval($r[0]['is_dir'])) {
        if ($recurse) {
            $r = q(
                "select hash, flags, is_dir from attach where folder = '%s' and uid = %d",
                dbesc($resource),
                intval($channel_id)
            );
            if ($r) {
                foreach ($r as $rr) {
                    attach_change_permissions($channel_id, $rr['hash'], $allow_cid, $allow_gid, $deny_cid, $deny_gid, $recurse, $sync);
                }
            }
        }
    }

    $x = q(
        "update attach set allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s', flags = 0 where hash = '%s' and uid = %d",
        dbesc($allow_cid),
        dbesc($allow_gid),
        dbesc($deny_cid),
        dbesc($deny_gid),
        dbesc($resource),
        intval($channel_id)
    );
    if ($r[0]['is_photo']) {
        $x = q(
            "update photo set allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s' where resource_id = '%s' and uid = %d",
            dbesc($allow_cid),
            dbesc($allow_gid),
            dbesc($deny_cid),
            dbesc($deny_gid),
            dbesc($resource),
            intval($channel_id)
        );
        $x = q(
            "update item set allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s' where resource_id = '%s' and resource_type = 'photo' and uid = %d",
            dbesc($allow_cid),
            dbesc($allow_gid),
            dbesc($deny_cid),
            dbesc($deny_gid),
            dbesc($resource),
            intval($channel_id)
        );
    }

    if ($sync) {
        $data = attach_export_data($channel, $resource);

        if ($data) {
            Libsync::build_sync_packet($channel['channel_id'], [ 'file' => [ $data ] ]);
        }
    }
}

/**
 * @brief Delete a file/directory from a channel.
 *
 * If the provided resource hash is from a directory it will delete everything
 * recursively under this directory.
 *
 * @param int $channel_id
 *  The id of the channel
 * @param string $resource
 *  The hash to delete
 * @param int $is_photo (optional) default 0
 * @return void
 */
function attach_delete($channel_id, $resource, $is_photo = 0)
{

    $c = q(
        "SELECT channel_address FROM channel WHERE channel_id = %d LIMIT 1",
        intval($channel_id)
    );

    $channel_address = (($c) ? $c[0]['channel_address'] : 'notfound');
    $photo_sql = (($is_photo) ? " and is_photo = 1 " : '');

    $r = q(
        "SELECT hash, os_storage, flags, is_dir, is_photo, folder FROM attach WHERE hash = '%s' AND uid = %d $photo_sql limit 1",
        dbesc($resource),
        intval($channel_id)
    );

    if (! $r) {
        attach_drop_photo($channel_id, $resource);
        $arr = ['channel_id' => $channel_id, 'resource' => $resource, 'is_photo' => $is_photo];
        Hook::call("attach_delete", $arr);
        return;
    }

    $url = get_cloud_url($channel_id, $channel_address, $resource);
    $object = get_file_activity_object($channel_id, $resource, $url);

    // If resource is a directory delete everything in the directory recursive
    if (intval($r[0]['is_dir'])) {
        $x = q(
            "SELECT hash, os_storage, is_dir, flags FROM attach WHERE folder = '%s' AND uid = %d",
            dbesc($resource),
            intval($channel_id)
        );
        if ($x) {
            foreach ($x as $xx) {
                attach_delete($channel_id, $xx['hash']);
            }
        }
    }

    // delete a file from filesystem
    if (intval($r[0]['os_storage'])) {
        $y = q(
            "SELECT content FROM attach WHERE hash = '%s' AND uid = %d LIMIT 1",
            dbesc($resource),
            intval($channel_id)
        );

        if ($y) {
            $f = dbunescbin($y[0]['content']);
            if (is_dir($f)) {
                @rmdir($f);
            } elseif (file_exists($f)) {
                unlink($f);
            }
        }
    }

    // delete from database
    $z = q(
        "DELETE FROM attach WHERE hash = '%s' AND uid = %d",
        dbesc($resource),
        intval($channel_id)
    );

    if ($r[0]['is_photo']) {
        attach_drop_photo($channel_id, $resource);
    }


    // update the parent folder's lastmodified timestamp
    $e = q(
        "UPDATE attach SET edited = '%s' WHERE hash = '%s' AND uid = %d",
        dbesc(datetime_convert()),
        dbesc($r[0]['folder']),
        intval($channel_id)
    );

    $arr = ['channel_id' => $channel_id, 'resource' => $resource, 'is_photo' => $is_photo];
    Hook::call("attach_delete", $arr);

    file_activity($channel_id, $object, $object['allow_cid'], $object['allow_gid'], $object['deny_cid'], $object['deny_gid'], 'update', true);

    return;
}


function attach_drop_photo($channel_id, $resource)
{

    $x = q(
        "select id, item_hidden from item where resource_id = '%s' and resource_type = 'photo' and uid = %d and item_deleted = 0",
        dbesc($resource),
        intval($channel_id)
    );
    if ($x) {
        drop_item($x[0]['id'], (($x[0]['item_hidden']) ? DROPITEM_NORMAL : DROPITEM_PHASE1), true);
    }
    $r = q(
        "select content from photo where uid = %d and resource_id = '%s' and os_storage = 1",
        intval($channel_id),
        dbesc($resource)
    );
    if ($r) {
        foreach ($r as $rv) {
            $p = dbunescbin($rv['content']);
            if ($p && file_exists($p)) {
                @unlink($p);
            }
        }
    }

    q(
        "DELETE FROM photo WHERE uid = %d AND resource_id = '%s'",
        intval($channel_id),
        dbesc($resource)
    );
}


/**
 * @brief Returns path to file in cloud/.
 *
 * @warning This function cannot be used with mod/dav as it always returns a
 * path valid under mod/cloud.
 *
 * @param array $arr associative array with:
 *  * \e int \b uid the channel's uid
 *  * \e string \b folder
 *  * \e string \b filename
 * @return string
 *  path to the file in cloud/
 */
function get_cloudpath($arr)
{
    $basepath = 'cloud/';

    if ($arr['uid']) {
        $r = q(
            "select channel_address from channel where channel_id = %d limit 1",
            intval($arr['uid'])
        );
        if ($r) {
            $basepath .= $r[0]['channel_address'] . '/';
        }
    }

    $path = $basepath;

    if ($arr['folder']) {
        $lpath = '';
        $lfile = $arr['folder'];

        do {
            $r = q(
                "select filename, hash, flags, is_dir, folder from attach where uid = %d and hash = '%s' and is_dir != 0
                    limit 1",
                intval($arr['uid']),
                dbesc($lfile)
            );

            if (! $r) {
                break;
            }

            if ($lfile) {
                $lpath = $r[0]['filename'] . '/' . $lpath;
            }
            $lfile = $r[0]['folder'];
        } while (($r[0]['folder']) && intval($r[0]['is_dir']));

        $path .= $lpath;
    }
    $path .= $arr['filename'];

    return $path;
}

/**
 * @brief Returns path to parent folder in cloud/.
 * This function cannot be used with mod/dav as it always returns a path valid under mod/cloud
 *
 * @param int $channel_id
 *  The id of the channel
 * @param string $channel_name
 *  The name of the channel
 * @param string $attachHash
 * @return string with the full folder path
 */
function get_cloud_url($channel_id, $channel_name, $attachHash)
{
    $parentFullPath = '';
    // build directory tree
    $parentHash = $attachHash;
    do {
        $parentHash = find_folder_hash_by_attach_hash($channel_id, $parentHash);
        if ($parentHash) {
            $parentName = find_filename_by_hash($channel_id, $parentHash);
            $parentFullPath = $parentName . '/' . $parentFullPath;
        }
    } while ($parentHash);


    $url = z_root() . '/cloud/' . $channel_name . '/' . $parentFullPath . find_filename_by_hash($channel_id, $attachHash);
    return $url;
}

/**
 * @brief Return the hash of an attachment's folder.
 *
 * @param int $channel_id
 *  The id of the channel
 * @param string $attachHash
 *  The hash of the attachment
 * @param bool $recurse
 *  (optional) default false
 * @return string
 */
function find_folder_hash_by_attach_hash($channel_id, $attachHash, $recurse = false)
{

    logger('attach_hash: ' . $attachHash);

    $r = q(
        "SELECT folder FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
        intval($channel_id),
        dbesc($attachHash)
    );
    $hash = EMPTY_STR;
    if ($r && $r[0]['folder']) {
        if ($recurse) {
            $hash = find_folder_hash_by_attach_hash($channel_id, $r[0]['folder'], true) . '/' . $r[0]['folder'];
        } else {
            $hash = $r[0]['folder'];
        }
    }

    return $hash;
}

/**
 * @brief Return the hash of an attachment's folder.
 *
 * @param int $channel_id
 * @param string $path
 * @return string
 */
function find_folder_hash_by_path($channel_id, $path)
{

    if (! $path) {
        return EMPTY_STR;
    }

    $comps        = explode('/', $path);
    $errors       = false;
    $parent_hash  = '';

    for ($x = 0; $x < count($comps); $x++) {
        $element = $comps[$x];
        $r = q(
            "SELECT hash FROM attach WHERE uid = %d AND filename = '%s' AND folder = '%s' LIMIT 1",
            intval($channel_id),
            dbesc($element),
            dbesc($parent_hash)
        );
        if ($r) {
            $parent_hash = $r[0]['hash'];
        } else {
            $errors++;
            break;
        }
    }

    if ($errors) {
        return EMPTY_STR;
    }

    return $parent_hash;
}

/**
 * @brief Returns the filename of an attachment in a given channel.
 *
 * @param int $channel_id
 *  The id of the channel
 * @param string $attachHash
 *  The hash of the attachment
 * @return string
 *  The filename of the attachment
 */
function find_filename_by_hash($channel_id, $attachHash)
{
    $r = q(
        "SELECT filename FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
        intval($channel_id),
        dbesc($attachHash)
    );
    $filename = '';
    if ($r) {
        $filename = $r[0]['filename'];
    }

    return $filename;
}

/**
 * @brief Activity for files.
 *
 * @param int $channel_id
 * @param array $object
 * @param string $allow_cid
 * @param string $allow_gid
 * @param string $deny_cid
 * @param string $deny_gid
 * @param string $verb
 * @param bool $notify
 */
function file_activity($channel_id, $object, $allow_cid, $allow_gid, $deny_cid, $deny_gid, $verb, $notify)
{

    $poster = App::get_observer();

    // if we got no object something went wrong
    if (!$object) {
        return;
    }

    // turn strings into arrays
    $arr_allow_cid = expand_acl($allow_cid);
    $arr_allow_gid = expand_acl($allow_gid);
    $arr_deny_cid = expand_acl($deny_cid);
    $arr_deny_gid = expand_acl($deny_gid);

    // filter out receivers which do not have permission to view filestorage
    $arr_allow_cid = check_list_permissions($channel_id, $arr_allow_cid, 'view_storage');

    $is_dir = (bool)intval($object['is_dir']);

    // do not send activity for folders for now
    if ($is_dir) {
        return;
    }

    // check for recursive perms if we are in a folder
    if ($object['folder']) {
        $folder_hash = $object['folder'];

        $r_perms = attach_recursive_perms($arr_allow_cid, $arr_allow_gid, $arr_deny_cid, $arr_deny_gid, $folder_hash);

        if ($r_perms === false) {
            // nobody has recursive perms - nobody must be notified
            return;
        }

        // split up returned perms
        $arr_allow_cid = $r_perms['allow_cid'];
        $arr_allow_gid = $r_perms['allow_gid'];
        $arr_deny_cid = $r_perms['deny_cid'];
        $arr_deny_gid = $r_perms['deny_gid'];

        // filter out receivers which do not have permission to view filestorage
        $arr_allow_cid = check_list_permissions($channel_id, $arr_allow_cid, 'view_storage');
    }

    $uuid = new_uuid();
    $mid = z_root() . '/item/' . $uuid;

    $objtype = 'Document';

    $arr = [];
    $arr['aid']           = get_account_id();
    $arr['uid']           = $channel_id;
    $arr['uuid']          = $uuid;
    $arr['item_wall']     = 1;
    $arr['item_origin']   = 1;
    $arr['item_unseen']   = 1;
    $arr['author_xchan']  = $poster['xchan_hash'];
    $arr['owner_xchan']   = $poster['xchan_hash']; //??
    $arr['title']         = $object['filename'];
    $arr['item_notshown'] = 1;
    $arr['obj_type']      = $objtype;
    $arr['resource_id']   = $object['hash'];
    $arr['resource_type'] = 'attach';

    $private = (($arr_allow_cid[0] || $arr_allow_gid[0] || $arr_deny_cid[0] || $arr_deny_gid[0]) ? 1 : 0);

    $obj = [
        'type' => 'Document',
        'id'   => z_root() . '/attach/' . $object['hash'],
        'name' => $object['filename'],
    ];

    $obj['url'] = array_merge($object['url'], [
        'type' => 'Link',
        'mediaType' => $object['filetype'],
        'href' => z_root() . '/attach/' . $object['hash']
    ]);


    $jsonobject = json_encode($obj);

    //check if item for this object exists
    $y = q(
        "SELECT mid FROM item WHERE verb = '%s' AND obj_type = '%s' AND resource_id = '%s' AND uid = %d LIMIT 1",
        dbesc(ACTIVITY_POST),
        dbesc($objtype),
        dbesc($object['hash']),
        intval(local_channel())
    );

    if ($y) {
        $update = true;
        $object['d_mid'] = $y[0]['mid']; //attach mid of the old object
        $u_jsonobject = json_encode($obj);

        // we have got the relevant info - delete the old item before we create the new one
        $z = q(
            "DELETE FROM item WHERE obj_type = '%s' AND verb = '%s' AND mid = '%s'",
            dbesc(ACTIVITY_OBJ_FILE),
            dbesc(ACTIVITY_POST),
            dbesc($y[0]['mid'])
        );
    }

    // send update activity and create a new one
    if ($update && $verb == 'post') {
        //updates should be sent to everybody with recursive perms and all eventual former allowed members ($object['allow_cid'] etc.).
        $u_arr_allow_cid = array_unique(array_merge($arr_allow_cid, expand_acl($object['allow_cid'])));
        $u_arr_allow_gid = array_unique(array_merge($arr_allow_gid, expand_acl($object['allow_gid'])));
        $u_arr_deny_cid = array_unique(array_merge($arr_deny_cid, expand_acl($object['deny_cid'])));
        $u_arr_deny_gid = array_unique(array_merge($arr_deny_gid, expand_acl($object['deny_gid'])));

        $private = (($u_arr_allow_cid[0] || $u_arr_allow_gid[0] || $u_arr_deny_cid[0] || $u_arr_deny_gid[0]) ? 1 : 0);

        $uuid = new_uuid();
        $u_mid = z_root() . '/item/' . $uuid;

        $arr['uuid']          = $uuid;
        $arr['mid']           = $u_mid;
        $arr['parent_mid']    = $u_mid;
        $arr['allow_cid']     = perms2str($u_arr_allow_cid);
        $arr['allow_gid']     = perms2str($u_arr_allow_gid);
        $arr['deny_cid']      = perms2str($u_arr_deny_cid);
        $arr['deny_gid']      = perms2str($u_arr_deny_gid);
        $arr['item_private']  = $private;
        $arr['verb']          = ACTIVITY_UPDATE;
        $arr['obj']           = $u_jsonobject;
        $arr['body']          = '';

        post_activity_item($arr);

        $update = false;
    }

    // don't create new activity if notify was not enabled
    if (! $notify) {
        return;
    }

    //don't create new activity if we have an update request but there is no item to update
    //this can e.g. happen when deleting images
    if ((! $y) && ($verb == 'update')) {
        return;
    }

    $arr['mid']           = $mid;
    $arr['parent_mid']    = $mid;
    $arr['allow_cid']     = perms2str($arr_allow_cid);
    $arr['allow_gid']     = perms2str($arr_allow_gid);
    $arr['deny_cid']      = perms2str($arr_deny_cid);
    $arr['deny_gid']      = perms2str($arr_deny_gid);
    $arr['item_private']  = $private;
    $arr['verb']          = (($update) ? ACTIVITY_UPDATE : ACTIVITY_POST);
    $arr['obj']           = (($update) ? $u_jsonobject : $jsonobject);
    $arr['body']          = '';

    post_activity_item($arr);

    return;
}

/**
 * @brief Create file activity object.
 *
 * @param int $channel_id
 * @param string $hash
 * @param string $url
 * @return array An associative array for the specified file.
 */
function get_file_activity_object($channel_id, $hash, $url)
{

    $x = q(
        "SELECT creator, filename, filetype, filesize, revision, folder, os_storage, is_photo, is_dir, flags, created, edited, allow_cid, allow_gid, deny_cid, deny_gid FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
        intval($channel_id),
        dbesc($hash)
    );
    if (! $x) {
        return null;
    }

    $url = rawurlencode($url);

    $links   = [];
    $links[] = [
        'rel'  => 'alternate',
        'mediaType' => 'text/html',
        'href' => $url
    ];

    $object = array_merge([
        'type'  => ACTIVITY_OBJ_FILE,
        'title' => $x[0]['filename'],
        'id'    => $url,
        'url'   => $links,
        'hash'  => $hash,
        ], $x[0]);

    return $object;
}

/**
 * @brief Returns recursive permissions array or false if nobody has recursive permissions
 *
 * @param array $arr_allow_cid
 * @param array $arr_allow_gid
 * @param array $arr_deny_cid
 * @param array $arr_deny_gid
 * @param string $folder_hash
 */
function attach_recursive_perms($arr_allow_cid, $arr_allow_gid, $arr_deny_cid, $arr_deny_gid, $folder_hash)
{

    $ret = [];
    $parent_arr = [];
    $count_values = [];
    $poster = App::get_observer();

    //lookup all channels in sharee group and add them to sharee $arr_allow_cid
    if ($arr_allow_gid) {
        $in_group = AccessList::expand($arr_allow_gid);
        $arr_allow_cid = array_unique(array_merge($arr_allow_cid, $in_group));
    }

    //count existing parent folders - we will compare to that count later
    $count = 0;
    while ($folder_hash) {
        $x = q(
            "SELECT allow_cid, allow_gid, deny_cid, deny_gid, folder FROM attach WHERE hash = '%s' LIMIT 1",
            dbesc($folder_hash)
        );

        //only process private folders
        if ($x[0]['allow_cid'] || $x[0]['allow_gid'] || $x[0]['deny_cid'] || $x[0]['deny_gid']) {
            $parent_arr['allow_cid'][] = expand_acl($x[0]['allow_cid']);
            $parent_arr['allow_gid'][] = expand_acl($x[0]['allow_gid']);
            $parent_arr['deny_cid'][] = expand_acl($x[0]['deny_cid']);
            $parent_arr['deny_gid'][] = expand_acl($x[0]['deny_gid']);

            //this is the number of all existing parent folders - we will compare to that count later
            $count++;
        }

        $folder_hash = $x[0]['folder'];
    }

    //logger(EOL . 'parent_arr: ' . print_r($parent_arr,true));

    //if none of the parent folders is private just return file perms
    if (!$parent_arr['allow_cid'] && !$parent_arr['allow_gid'] && !$parent_arr['deny_cid'] && !$parent_arr['deny_gid']) {
        $ret['allow_gid'] = $arr_allow_gid;
        $ret['allow_cid'] = $arr_allow_cid;
        $ret['deny_gid'] = $arr_deny_gid;
        $ret['deny_cid'] = $arr_deny_cid;

        return $ret;
    }

    //if there are no perms on the file we will work with the perms from the first parent folder
    if (!$arr_allow_cid && !$arr_allow_gid && !$arr_deny_cid && !$arr_deny_gid) {
        $arr_allow_cid = $parent_arr['allow_cid'][0];
        $arr_allow_gid = $parent_arr['allow_gid'][0];
        $arr_deny_cid = $parent_arr['deny_cid'][0];
        $arr_deny_gid = $parent_arr['deny_gid'][0];
    }


    /***
    *
    * check if sharee has perms for all parent folders
    *
    ***/

    $r_arr_allow_cid = [];

    if ($parent_arr['allow_cid']) {
        //check sharee arr_allow_cid against allow_cid of all parent folders
        foreach ($parent_arr['allow_cid'] as $folder_arr_allow_cid) {
            foreach ($folder_arr_allow_cid as $ac_hash) {
                $count_values[$ac_hash]++;
            }
        }
        foreach ($arr_allow_cid as $fac_hash) {
            if ($count_values[$fac_hash] == $count) {
                $r_arr_allow_cid[] = $fac_hash;
            }
        }
        //logger(EOL . 'r_arr_allow_cid: ' . print_r($r_arr_allow_cid,true));
    }

    if ($parent_arr['allow_gid']) {
        //check sharee arr_allow_cid against members of allow_gid of all parent folders
        foreach ($parent_arr['allow_gid'] as $folder_arr_allow_gid) {
            //get the group members
            $folder_arr_allow_cid = AccessList::expand($folder_arr_allow_gid);
            foreach ($folder_arr_allow_cid as $ac_hash) {
                $count_values[$ac_hash]++;
            }
        }
        foreach ($arr_allow_cid as $fac_hash) {
            if ($count_values[$fac_hash] == $count) {
                $r_arr_allow_cid[] = $fac_hash;
            }
        }
        //logger(EOL . 'groups - r_arr_allow_cid: ' . print_r($r_arr_allow_cid,true));
    }


    /***
    *
    * check if sharee is denied somewhere in parent folders and deny him if so
    *
    ***/

    //deny_cid
    $r_arr_deny_cid = [];

    if ($parent_arr['deny_cid']) {
        foreach ($parent_arr['deny_cid'] as $folder_arr_deny_cid) {
            $r_arr_deny_cid = array_merge($arr_deny_cid, $folder_arr_deny_cid);
        }
        $r_arr_deny_cid = array_unique($r_arr_deny_cid);
        //logger(EOL . 'r_arr_deny_cid: ' . print_r($r_arr_deny_cid,true));
    }

    //deny_gid
    $r_arr_deny_gid = [];

    if ($parent_arr['deny_cid']) {
        foreach ($parent_arr['deny_gid'] as $folder_arr_deny_gid) {
            $r_arr_deny_gid = array_merge($arr_deny_gid, $folder_arr_deny_gid);
        }
        $r_arr_deny_gid = array_unique($r_arr_deny_gid);
        //logger(EOL . 'r_arr_deny_gid: ' . print_r($r_arr_dr_arr_deny_gideny_cid,true));
    }

    //if no channel is allowed return false
    if (! $r_arr_allow_cid) {
        return false;
    }

    $ret['allow_gid'] = []; // eventual group members are already collected in $r_arr_allow_cid
    $ret['allow_cid'] = $r_arr_allow_cid;
    $ret['deny_gid'] = $r_arr_deny_gid;
    $ret['deny_cid'] = $r_arr_deny_cid;

    return $ret;
}

function filepath_macro($s)
{
    return str_replace(
        [ '%Y', '%m', '%d' ],
        [ datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y'),
          datetime_convert('UTC', date_default_timezone_get(), 'now', 'm'),
          datetime_convert('UTC', date_default_timezone_get(), 'now', 'd')
        ],
        $s
    );
}

function attach_export_data($channel, $resource_id, $deleted = false)
{

    $ret = [];
    $paths = [];

    $hash_ptr = $resource_id;

    $ret['fetch_url'] = z_root() . '/getfile';
    $ret['original_channel'] = $channel['channel_address'];


    if ($deleted) {
        $ret['attach'] = [ [ 'hash' => $resource_id, 'deleted' => 1 ] ];
        return $ret;
    }

    do {
        $r = q(
            "select * from attach where hash = '%s' and uid = %d limit 1",
            dbesc($hash_ptr),
            intval($channel['channel_id'])
        );
        if (! $r) {
            break;
        }

        if ($hash_ptr === $resource_id) {
            $attach_ptr = $r[0];
        }
        $r[0]['content'] = dbunescbin($r[0]['content']);

        $hash_ptr = $r[0]['folder'];
        $paths[] = $r[0];
    } while ($hash_ptr);


    $paths = array_reverse($paths);

    $ret['attach'] = $paths;


    if ($attach_ptr['is_photo']) {
        $r = q(
            "select * from photo where resource_id = '%s' and uid = %d order by imgscale asc",
            dbesc($resource_id),
            intval($channel['channel_id'])
        );
        if ($r) {
            for ($x = 0; $x < count($r); $x++) {
                $r[$x]['content'] = base64_encode(dbunescbin($r[$x]['content']));
            }
            $ret['photo'] = $r;
        }

        $r = q(
            "select * from item where resource_id = '%s' and resource_type = 'photo' and uid = %d ",
            dbesc($resource_id),
            intval($channel['channel_id'])
        );
        if ($r) {
            $ret['item'] = [];
            $items = q(
                "select item.*, item.id as item_id from item where item.parent = %d ",
                intval($r[0]['id'])
            );
            if ($items) {
                xchan_query($items);
                $items = fetch_post_tags($items);
                foreach ($items as $rr) {
                    $ret['item'][] = encode_item($rr, true);
                }
            }
        }
    }
    return $ret;
}


/**
 * @brief Strip off 'store/nickname/' from the provided path
 *
 * @param string $s
 * @return string
 */
function get_attach_binname($s)
{
    $p = $s;
    if (str_starts_with($s, 'store/')) {
        $p = substr($s, 6);
        $p = substr($p, strpos($p, '/') + 1);
    }
    return $p;
}


function get_dirpath_by_cloudpath($channel, $path)
{

    $path = notags(trim($path));

    $h = @parse_url($path);

    if (! $h || !x($h, 'path')) {
        return null;
    }
    if (str_ends_with($h['path'], '/')) {
        $h['path'] = substr($h['path'], 0, -1);
    }
    if (str_starts_with($h['path'], '/')) {
        $h['path'] = substr($h['path'], 1);
    }
    $folders = explode('/', $h['path']);
    $nick = $channel['channel_address'];

    if (count($folders) > 1 && $folders[0] === 'cloud' && $folders[1] === $nick) {
        array_shift($folders);
        array_shift($folders);
    }
    $clouddir = 'store/' . $nick . '/' ;
    $subdir = '/';
    $valid = true;
    while ($folders && $valid && is_dir($clouddir . $subdir) && is_readable($clouddir . $subdir)) {
        $valid = false;
        $f = array_shift($folders);
        $items = array_diff(scandir($clouddir . $subdir), ['.', '..']); // hashed names
        foreach ($items as $item) {
            $filename = find_filename_by_hash($channel['channel_id'], $item);
            if ($filename === $f) {
                $subdir .= $item . '/';
                $valid = true;
            }
        }
    }
    if (! $valid) {
        return null;
    } else {
        return $clouddir . $subdir;
    }
}

function get_filename_by_cloudname($cloudname, $channel, $storepath)
{
    $items = array_diff(scandir($storepath), ['.', '..']); // hashed names
    foreach ($items as $item) {
        $filename = find_filename_by_hash($channel['channel_id'], $item);
        if ($filename === $cloudname) {
            return $item;
        }
    }
    return null;
}

/**
 * @brief recursively copy a directory into cloud files
 *
 * @param array $channel
 * @param string $observer_hash
 * @param string $srcpath
 * @param string $cloudpath
 * @return bool
 */
function copy_folder_to_cloudfiles($channel, $observer_hash, $srcpath, $cloudpath)
{
    if (!is_dir($srcpath) || !is_readable($srcpath)) {
        logger('Error reading source path: ' . $srcpath, LOGGER_NORMAL);
        return false;
    }
    $nodes = array_diff(scandir($srcpath), ['.', '..']);
    foreach ($nodes as $node) {
        $clouddir = $cloudpath . '/' . $node;  // Sub-folder in cloud files destination
        $nodepath = $srcpath . '/' . $node;    // Sub-folder in source path
        if (is_dir($nodepath)) {
            $x = attach_mkdirp($channel, $observer_hash, ['pathname' => $clouddir]);
            if (!$x['success']) {
                logger('Error creating cloud path: ' . $clouddir, LOGGER_NORMAL);
                return false;
            }
            // Recursively call this function where the source and destination are the subfolders
            $success = copy_folder_to_cloudfiles($channel, $observer_hash, $nodepath, $clouddir);
            if (! $success) {
                logger('Error copying contents of folder: ' . $nodepath, LOGGER_NORMAL);
                return false;
            }
        } elseif (is_file($nodepath) && is_readable($nodepath)) {
            $x = attach_store($channel, $observer_hash, 'import', [
                'directory' => $cloudpath,
                'src' => $nodepath,
                'filename' => $node,
                'filesize' => @filesize($nodepath),
                'preserve_original' => true
            ]);
            if (! $x['success']) {
                logger('Error copying file: ' . $nodepath, LOGGER_NORMAL);
                logger('Return value: ' . json_encode($x), LOGGER_NORMAL);
                return false;
            }
        } else {
            logger('Error scanning source path', LOGGER_NORMAL);
            return false;
        }
    }

    return true;
}

/**
 * This function performs an in place directory-to-directory move of a stored attachment or photo.
 * The data is physically moved in the store/nickname storage location and the paths adjusted
 * in the attach structure (and if applicable the photo table). The new 'album name' is recorded
 * for photos and will show up immediately there.
 * This takes a channel_id, attach.hash of the file to move (this is the same as a photo resource_id), and
 * the attach.hash of the new parent folder, which must already exist. If $new_folder_hash is blank or empty,
 * the file is relocated to the root of the channel's storage area.
 *
 *
 * @param int $channel_id
 * @param int $resource_id
 * @param string $new_folder_hash
 * @return void|bool
 */

function attach_move($channel_id, $resource_id, $new_folder_hash, $newname = '')
{

    $c = Channel::from_id($channel_id);
    if (! ($c && $resource_id)) {
        return false;
    }


    // find the resource to be moved

    $r = q(
        "select * from attach where hash = '%s' and uid = %d limit 1",
        dbesc($resource_id),
        intval($channel_id)
    );
    if (! $r) {
        logger('resource_id not found');
        return false;
    }

    $oldstorepath = dbunescbin($r[0]['content']);


    // find the resource we are moving to

    if ($new_folder_hash) {
        $n = q(
            "select * from attach where hash = '%s' and uid = %d and is_dir = 1 limit 1",
            dbesc($new_folder_hash),
            intval($channel_id)
        );
        if (! $n) {
            return false;
        }

        $newdirname = $n[0]['filename'];
        $newalbumname = $n[0]['display_path'];
        $newstorepath = dbunescbin($n[0]['content']) . '/' . $resource_id;
    } else {
        // root directory

        $newdirname = EMPTY_STR;
        $newalbumname = EMPTY_STR;
        $newstorepath = 'store/' . $c['channel_address'] . '/' . $resource_id;
    }

    rename($oldstorepath, $newstorepath);

    // duplicate detection. If 'overwrite' is specified, return false because we can't yet do that.

    $oldfilename = $r[0]['filename'];
    $newfilename = (($newname) ? basename($newname) : $oldfilename);

    // don't do duplicate check unless our parent folder has changed.

    if ($r[0]['folder'] !== $new_folder_hash) {
        $s = q(
            "select filename, id, hash, filesize from attach where filename = '%s' and folder = '%s' ",
            dbesc($newfilename),
            dbesc($new_folder_hash)
        );

        if ($s) {
            $overwrite = get_pconfig($channel_id, 'system', 'overwrite_dup_files');
            if ($overwrite) {
                /// @fixme
                return;
            } else {
                if (str_contains($newfilename, '.')) {
                    $basename = substr($newfilename, 0, strrpos($newfilename, '.'));
                    $ext = substr($newfilename, strrpos($newfilename, '.'));
                } else {
                    $basename = $newfilename;
                    $ext = '';
                }

                $matches = false;
                if (preg_match('/(.*?)\([0-9]+\)$/', $basename, $matches)) {
                    $basename = $matches[1];
                }

                $v = q(
                    "select filename from attach where ( filename = '%s' OR filename like '%s' ) and folder = '%s' ",
                    dbesc($basename . $ext),
                    dbesc($basename . '(%)' . $ext),
                    dbesc($new_folder_hash)
                );

                if ($v) {
                    $x = 1;

                    do {
                        $found = false;
                        foreach ($v as $vv) {
                            if ($vv['filename'] === $basename . '(' . $x . ')' . $ext) {
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            $x++;
                        }
                    } while ($found);

                    $newfilename = $basename . '(' . $x . ')' . $ext;
                } else {
                    $newfilename = $basename . $ext;
                }
            }
        }
    }

    $t = q(
        "update attach set content = '%s', folder = '%s', filename = '%s' where id = %d",
        dbescbin($newstorepath),
        dbesc($new_folder_hash),
        dbesc($newfilename),
        intval($r[0]['id'])
    );


    $x = attach_syspaths($channel_id, $resource_id);

    $t1 = q(
        "update attach set os_path = '%s', display_path = '%s' where id = %d",
        dbesc($x['os_path']),
        dbesc($x['path']),
        intval($r[0]['id'])
    );


    if ($r[0]['is_photo']) {
        $t = q(
            "update photo set album = '%s', filename = '%s', os_path = '%s', display_path = '%s'
                where resource_id = '%s' and uid = %d",
            dbesc($newalbumname),
            dbesc($newfilename),
            dbesc($x['os_path']),
            dbesc($x['path']),
            dbesc($resource_id),
            intval($channel_id)
        );

        $t = q(
            "update photo set content = '%s' where resource_id = '%s' and uid = %d and imgscale = 0",
            dbescbin($newstorepath),
            dbesc($resource_id),
            intval($channel_id)
        );
    }

    if ($r[0]['is_dir']) {
        $move_success = true;
        $x = q(
            "select hash from attach where folder = '%s' and uid = %d",
            dbesc($r[0]['hash']),
            intval($channel_id)
        );
        if ($x) {
            foreach ($x as $xv) {
                $rs = attach_move($channel_id, $xv['hash'], $r[0]['hash']);
                if (! $rs) {
                    $move_success = false;
                    break;
                }
            }
        }
        return $move_success;
    }

    return true;
}


/**
 * Used to generate a select input box of all your folders
 */


function attach_folder_select_list($channel_id)
{

    $r = q(
        "select * from attach where is_dir = 1 and uid = %d",
        intval($channel_id)
    );

    $out = [];
    $out[''] = '/';

    if ($r) {
        foreach ($r as $rv) {
            $x = attach_folder_rpaths($r, $rv);
            if ($x) {
                $out[$x[0]] = $x[1];
            }
        }
    }

    return $out;
}

function attach_folder_rpaths($all_folders, $that_folder)
{

    $path         = '/' . $that_folder['filename'];
    $current_hash = $that_folder['hash'];
    $parent_hash  = $that_folder['folder'];
    $error        = false;
    $found        = false;

    if ($parent_hash) {
        do {
            foreach ($all_folders as $selected) {
                if (! $selected['is_dir']) {
                    continue;
                }
                if ($selected['hash'] == $parent_hash) {
                    $path         = '/' . $selected['filename'] . $path;
                    $parent_hash  = $selected['folder'];
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $error = true;
            }
        } while ((! $found) && (! $error) && ($parent_hash != ''));
    }

    return (($error) ? false : [ $current_hash , $path ]);
}

/**
 * @brief Given a channel_id and attach_hash,  return an array with the full relative path and os_path
 */


function attach_syspaths($channel_id, $attach_hash)
{

    $os_path = '';
    $path = '';
    do {
        $r = q(
            "select folder, filename, hash from attach where hash = '%s' and uid = %d",
            dbesc($attach_hash),
            intval($channel_id)
        );
        if (! $r) {
            break;
        }

        $os_path = $r[0]['hash'] . (($os_path) ? '/' . $os_path : '');
        $path = $r[0]['filename'] . (($path) ? '/' . $path : '');
        $attach_hash = $r[0]['folder'];
    } while ($attach_hash);

    return [ 'os_path' => $os_path, 'path' => $path ];
}

/**
 * in earlier releases we did not fill in os_path and display_path in the attach DB structure.
 * (It was not needed or used). Going forward we intend to make use of these fields.
 * A cron task checks for empty values (as older attachments may have arrived at our site
 * in a clone operation) and executes attach_syspaths() to generate these field values and correct
 * the attach table entry. The operation is limited to 100 DB entries at a time so as not to
 * overload the system in any cron run. Eventually it will catch up with old attach structures
 * and switch into maintenance mode to correct any that might arrive in clone packets from older
 * sites.
 */


function attach_upgrade()
{

    $r = q("select id, uid, hash from attach where os_path = '' and display_path = '' limit 100");
    if ($r) {
        foreach ($r as $rv) {
            $x = attach_syspaths($rv['uid'], $rv['hash']);
            if ($x) {
                $w = q(
                    "update attach set os_path = '%s', display_path = '%s' where id = %d",
                    dbesc($x['os_path']),
                    dbesc($x['path']),
                    intval($rv['id'])
                );
                $y = q(
                    "update photo set os_path = '%s', display_path = '%s' where uid = %d and resource_id = '%s'",
                    dbesc($x['os_path']),
                    dbesc($x['path']),
                    intval($rv['uid']),
                    dbesc($rv['hash'])
                );
            }
        }
    }
}


/**
 * Chunked uploader for integration with the blueimp jquery-uploader
 * This is currently used.
 */


function save_chunk($channel, $start, $end, $len)
{

    $result = [];

    $tmp_path = $_FILES['files']['tmp_name'];
    $new_base = 'cache/' . $channel['channel_address'] . '/tmp';

    Stdio::mkdir($new_base, STORAGE_DEFAULT_PERMISSIONS, true);

    if (! is_dir($new_base)) {
        logger('directory create failed for ' . $new_base);
    }

    $new_path = $new_base . '/' . $_FILES['files']['name'] . '.ftmp';

    if (file_exists($new_path) && intval($start) === 0) {
        $result['partial'] = true;
        $result['length']  = intval(filesize($new_path));
        return $result;
    }

    if (! file_exists($new_path)) {
        rename($tmp_path, $new_path);
    } else {
        Stdio::fcopy($tmp_path, $new_path, write_mode: 'ab');
    }
    if (($len - 1) == $end) {
        unlink($tmp_path);
        $result['name']     = $_FILES['files']['name'];
        $result['type']     = $_FILES['files']['type'];
        $result['tmp_name'] = $new_path;
        $result['error']    = 0;
        $result['size']     = $len;
        $result['complete'] = true;
        return $result;
    }
    $result['partial'] = true;
    $result['length']  = intval(filesize($new_path));
    return $result;
}
