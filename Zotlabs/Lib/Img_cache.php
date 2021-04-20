<?php
namespace Zotlabs\Lib;

use Zotlabs\Lib\Hashpath;
use Zotlabs\Daemon\Run;

class Img_cache {

	static $cache_life = 18600 * 7;

	static function get_filename($url, $prefix = '.') {
		return Hashpath::path($url,$prefix);
	}

	static function check($url, $prefix = '.') {

		if (strpos($url,z_root()) !== false) {
			return false;
		}

		$path = self::get_filename($url,$prefix);
		if (file_exists($path)) {
			$t = filemtime($path);
			if ($t && time() - $t >= self::$cache_life) {
				if (self::url_to_cache($url,$path)) {
					return true;
				}
				return false;
			}
			return true;
		}

		return self::url_to_cache($url,$path);
	}

	static function url_to_cache($url,$file) {

		$fp = fopen($file,'wb');

		if (! $fp) {
			logger('failed to open storage file: ' . $file,LOGGER_NORMAL,LOG_ERR);
			return false;
		}
		
		$redirects = 0;
		$x = z_fetch_url($url,true,$redirects,[ 'filep' => $fp, 'novalidate' => true ]);

		fclose($fp);
		
		if ($x['success'] && file_exists($file)) {
			$i = @getimagesize($file);
			if ($i && $i[2]) {  // looking for non-zero imagetype
				Run::Summon( [ 'CacheThumb' , basename($file) ] );
				return true;
			}
		}

		// We could not cache the image for some reason. Leave an empty file here
		// to provide a record of the attempt. We'll use this as a flag to avoid
		// doing it again repeatedly.

		file_put_contents($file, EMPTY_STR);
		logger('cache failed ' . $file);
		return false;
	}

}







