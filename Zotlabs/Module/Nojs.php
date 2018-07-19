<?php
namespace Zotlabs\Module;


class Nojs extends \Zotlabs\Web\Controller {

	function init() {
		$n = ((argc() > 1) ? intval(argv(1)) : 1);
		setcookie('jsdisabled', $n, 0, '/');
		$p = hex2bin($_GET['redir']);
		$hasq = strpbrk($p,'?&');
		goaway(z_root() . (($p) ? '/' . $p : '') . (($hasq) ? '' : '?f=' ) . '&jsdisabled=' . $n);
	
	}
}
