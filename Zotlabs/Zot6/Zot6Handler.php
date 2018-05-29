<?php

namespace Zotlabs\Zot6;

class Zot6Handler implements IHandler {

	function Notify($data) {
		zot_reply_notify($data,$hubs);
	}

	function Request($data) {
		zot_reply_message_request($data,$hubs);
	}

	function Rekey($sender,$data) {
		zot_rekey_request($sender,$data,$hubs);
	}

	function Refresh($sender,$recipients) {
		zot_reply_refresh($sender,$recipients,$hubs);
	}

	function Purge($sender,$recipients) {
		zot_reply_purge($sender,$recipients,$hubs);
	}

}
