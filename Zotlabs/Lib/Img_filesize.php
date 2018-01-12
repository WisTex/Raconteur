<?php

namespace Zotlabs\Lib;

class Img_filesize {

	private $url;

	function __construct($url) {
		$this->url = $url;
	}

	function getSize() {
		$size = null;

		if(stripos($this->url,z_root() . '/photo') !== false) {
			$size = self::getLocalFileSize($this->url);
		}
		if(! $size) {
			$size = getRemoteFileSize($this->url);
		}

		return $size;
	}


	static function getLocalFileSize($url) {
	
		$fname = basename($url);
		$resolution = 0;
	
		if(strpos($fname,'.') !== false)
			$fname = substr($fname,0,strpos($fname,'.'));
	
		if(substr($fname,-2,1) == '-') {
			$resolution = intval(substr($fname,-1,1));
			$fname = substr($fname,0,-2);
		}
			
		$r = q("SELECT filesize FROM photo WHERE resource_id = '%s' AND imgscale = %d LIMIT 1",
			dbesc($fname),
			intval($resolution)
		);
		if($r) {
			return $r[0]['filesize'];
		}
		return null;
	}

}

/**
 * Try to determine the size of a remote file by making an HTTP request for
 * a byte range, or look for the content-length header in the response.
 * The function aborts the transfer as soon as the size is found, or if no
 * length headers are returned, it aborts the transfer.
 *
 * @return int|null null if size could not be determined, or length of content
 */
function getRemoteFileSize($url)
{
    $ch = curl_init($url);

    $headers = array(
        'Range: bytes=0-1',
        'Connection: close',
    );

    $in_headers = true;
    $size       = null;

    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2450.0 Iron/46.0.2450.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, 0); // set to 1 to debug
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://output', 'r'));

    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $line) use (&$in_headers, &$size) {
        $length = strlen($line);

        if (trim($line) == '') {
            $in_headers = false;
        }

        list($header, $content) = explode(':', $line, 2);
        $header = strtolower(trim($header));

        if ($header == 'content-range') {
            // found a content-range header
            list($rng, $s) = explode('/', $content, 2);
            $size = (int)$s;
            return 0; // aborts transfer
        } else if ($header == 'content-length' && 206 != curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
            // found content-length header and this is not a 206 Partial Content response (range response)
            $size = (int)$content;
            return 0;
        } else {
            // continue
            return $length;
        }
    });

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use ($in_headers) {
        if (!$in_headers) {
            // shouldn't be here unless we couldn't determine file size
            // abort transfer
            return 0;
        }

        // write function is also called when reading headers
        return strlen($data);
    });

    curl_exec($ch);
    curl_getinfo($ch);
	curl_close($ch);

    return $size;
}