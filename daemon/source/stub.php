#!/usr/bin/env php
<?php

use LinuxDr\VagrantSync\Application\Runner;

require_once Phar::running() . '/autoloader.php';

Runner::launch($argv);

__HALT_COMPILER();