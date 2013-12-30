#!/usr/bin/env php
<?php

use LinuxDr\VagrantSync\Application\Runner;

require_once 'phar://' . __FILE__ . '/autoloader.php';

$runner = new Runner($argv);
$runner->launch();

__HALT_COMPILER();