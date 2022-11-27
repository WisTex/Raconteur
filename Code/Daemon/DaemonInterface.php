<?php

namespace Code\Daemon;

interface DaemonInterface
{
    public function run(int $argc, array $argv): void;
}
