<?php

namespace Zotlabs\Zot6;

class Zot6Handler implements IHandler {

	function Notify($data) {
		zot_reply_notify($data);
	}

	function Request($data) {
		zot_reply_message_request($data);
	}

	function Rekey($sender,$data) {
		zot_rekey_request($sender,$data);
	}

	function Refresh($sender,$recipients) {
		zot_reply_refresh($sender,$recipients);
	}

	function Purge($sender,$recipients) {
		zot_reply_purge($sender,$recipients);
	}

}
