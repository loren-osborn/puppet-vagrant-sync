#!/usr/bin/env php
<?php

use LinuxDr\VagrantSync\Application\Runner;

require_once "phar://vagrant_sync.phar/autoloader.php";

Runner::launch($argv);

__HALT_COMPILER();