<?php

namespace Zotlabs\Zot6;

interface IHandler
{

    public function Notify($data, $hub);

    public function Rekey($sender, $data, $hub);

    public function Refresh($sender, $recipients, $hub, $force);

    public function Purge($sender, $recipients, $hub);

}

