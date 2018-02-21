<?php

namespace Zotlabs\Update;

class _1115 {
function run() {

	// Introducing email verification. Mark all existing accounts as verified or they
	// won't be able to login.

	$r = q("update account set account_flags = (account_flags ^ 1) where (account_flags & 1) ");
	return UPDATE_SUCCESS;
}


}