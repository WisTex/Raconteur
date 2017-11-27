<?php
namespace Zotlabs\Module;

// See update_profile.php for documentation


class Update_pubstream extends \Zotlabs\Web\Controller {

	function get() {

		$profile_uid = ((intval($_GET['p'])) ? intval($_GET['p']) : (-1));
		$load = (((argc() > 1) && (argv(1) == 'load')) ? 1 : 0);
		header("Content-type: text/html");
		echo "<!DOCTYPE html><html><body>\r\n";
		echo ((array_key_exists('msie',$_GET) && $_GET['msie'] == 1) ? '<div>' : '<section>');
	
		$mod = new Pubstream();
		$text = $mod->get($profile_uid, $load);

		echo str_replace("\t",'       ',$text);
		echo ((array_key_exists('msie',$_GET) && $_GET['msie'] == 1) ? '</div>' : '</section>');
		echo "</body></html>\r\n";
		killme();
	
	}
}
