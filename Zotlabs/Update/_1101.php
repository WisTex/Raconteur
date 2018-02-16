<?php

namespace Zotlabs\Update;

class _1101 {
function run() {
	$r = q("update updates set ud_flags = 2 where ud_flags = (-1)");
	$r = q("update updates set ud_flags = 0 where ud_flags = 4096");
	return UPDATE_SUCCESS;
}


}