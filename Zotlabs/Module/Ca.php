<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Ca extends Controller {

	function get() {
		if (argc() > 1) {
			$path = 'cache/img/' . substr(argv(1),0,2) . '/' . argv(1);

			if (file_exists($path)) {
				$x = @getimagesize($path);
				if ($x) {
					header('Content-Type: ' . $x['mime']);
				}
				$infile = fopen($path,'rb');
				$outfile = fopen('php://output','wb');
				pipe_streams($infile,$outfile);
				fclose($infile);
				fclose($outfile);
				killme();
			}
			if ($_GET['url']) {
				goaway($url);
			}
		}
		http_status_exit(404,'Not found');
	}
}