<?php
namespace LinuxDir\VagrantSync\Test\Application;

use PHPUnit_Framework_TestCase;
use LinuxDr\VagrantSync\Application\MainLoop;

require_once 'phar://' . dirname(dirname(dirname(__FILE__))) . "/build/vagrant_sync.phar/autoloader.php";
require_once "PHPUnit/Autoload.php";

class MainLoopTest extends PHPUnit_Framework_TestCase
{
	public function assertPreConditions()
	{
		$this->assertTrue(class_exists('\\LinuxDr\\VagrantSync\\Application\\MainLoop'), "Test file loading");
	}

	public function testExists()
	{
	}
}
