<?php

namespace Zotlabs\Update;

class _1103 {
function run() {
	$x = curl_version();
	if(stristr($x['ssl_version'],'openssl'))
		set_config('system','curl_ssl_ciphers','ALL:!eNULL');
	return UPDATE_SUCCESS;
}


}