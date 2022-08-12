<?php

namespace Code\Web;

/**
 * @file index.php
 *
 * @brief The main entry point to the application.
 */

require_once 'Code/Web/WebServer.php';

$server = new WebServer();
$server->run();
