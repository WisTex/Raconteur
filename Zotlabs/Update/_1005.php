<?php

namespace Zotlabs\Update;

class _1005 {
function run() {
	q("drop table guid");
	q("drop table `notify-threads`");
	return UPDATE_SUCCESS;
}


}