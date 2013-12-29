#!/usr/bin/env php
<?php

use LinuxDr\VagrantSync\Application\Runner;

require_once 'phar://' . __FILE__ . '/autoloader.php';

Runner::launch($argv);

__HALT_COMPILER();