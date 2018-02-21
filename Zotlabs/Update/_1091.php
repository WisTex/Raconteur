<?php

namespace Zotlabs\Update;

class _1091 {
function run() {
	@os_mkdir('store/[data]/smarty3',STORAGE_DEFAULT_PERMISSIONS,true);
	@file_put_contents('store/[data]/locks','');
	return UPDATE_SUCCESS;
}


}