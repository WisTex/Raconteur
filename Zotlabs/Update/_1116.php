<?php

namespace Zotlabs\Update;

class _1116 {
function run() {
	@os_mkdir('cache/smarty3',STORAGE_DEFAULT_PERMISSIONS,true);
	return UPDATE_SUCCESS;
} 


}