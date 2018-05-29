<?php

namespace Zotlabs\Zot6;

interface IHandler {

	function Notify($data,$hubs);

	function Request($data,$hubs);

	function Rekey($sender,$data,$hubs);

	function Refresh($sender,$recipients,$hubs);

	function Purge($sender,$recipients,$hubs);

}

