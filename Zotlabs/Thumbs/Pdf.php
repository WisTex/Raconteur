<?php

namespace Zotlabs\Thumbs;


class Pdf {

	function Match($type) {
		return(($type === 'application/pdf') ? true : false );
	}

	function Thumb($attach,$preview_style,$height = 300, $width = 300) {

		$photo = false;

		$file = dbunescbin($attach['content']);
		$tmpfile = $file . '.pdf';
		$outfile = $file . '.jpg';

		$istream = fopen($file,'rb');
		$ostream = fopen($tmpfile,'wb');
		if($istream && $ostream) {
			pipe_streams($istream,$ostream);
			fclose($istream);
			fclose($ostream);
		}

		$imagick_path = get_config('system','imagick_convert_path');
		if($imagick_path && @file_exists($imagick_path)) {
			$cmd = $imagick_path . ' ' . escapeshellarg(PROJECT_BASE . '/' . $tmpfile . '[0]') . ' -resize ' . $width . 'x' . $height . ' ' . escapeshellarg(PROJECT_BASE . '/' . $outfile);
			//  logger('imagick thumbnail command: ' . $cmd);
			for($x = 0; $x < 4; $x ++) {
				exec($cmd);
				if(! file_exists($outfile)) {
					logger('imagick scale failed. Retrying.');
					continue;
				}
			}
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

