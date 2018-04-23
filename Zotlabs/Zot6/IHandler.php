<?php

namespace Zotlabs\Zot6;

interface IHandler {

	function Notify($data);

	function Request($data);

	function Rekey($sender,$data);

	function Refresh($sender,$recipients);

	function Purge($sender,$recipients);

}

