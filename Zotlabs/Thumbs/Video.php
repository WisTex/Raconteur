<?php

namespace Zotlabs\Thumbs;


class Video {

	function MatchDefault($type) {
		return(($type === 'video') ? true : false );
	}

	function Thumb($attach,$preview_style,$height = 300, $width = 300) {

		$photo = false;

		$t = explode('/',$attach['filetype']);
		if($t[1])
			$extension = '.' . $t[1];
		else
			return; 


		$file = dbunescbin($attach['content']);
		$tmpfile = $file . $extension;
		$outfile = $file . '.jpg';

		$istream = fopen($file,'rb');
		$ostream = fopen($tmpfile,'wb');
		if($istream && $ostream) {
			pipe_streams($istream,$ostream);
			fclose($istream);
			fclose($ostream);
		}

		/*
		 * Note: imagick convert may try to call 'ffmpeg' (or other conversion utilities) under
		 * the covers for this particular operation. If this is not installed or not in the path
		 * for the web server user, errors may be reported in the web server logs.
		 */


		$ffmpeg = trim(shell_exec('which ffmpeg'));
		if($ffmpeg) { 
			logger('ffmpeg not found in path. Video thumbnails may fail.');
		}

		$imagick_path = get_config('system','imagick_convert_path');
		if($imagick_path && @file_exists($imagick_path)) {
			$cmd = $imagick_path . ' ' . escapeshellarg(PROJECT_BASE . '/' . $tmpfile . '[0]') . ' -resize ' . $width . 'x' . $height . ' ' . escapeshellarg(PROJECT_BASE . '/' . $outfile);
			//  logger('imagick thumbnail command: ' . $cmd);

			/** @scrutinizer ignore-unhandled */
			@exec($cmd);

			if(! file_exists($outfile)) {
				logger('imagick scale failed.');
			}
			else {
				@rename($outfile,$file . '.thumb');
			}
		}
	
		@unlink($tmpfile);
	}
}

