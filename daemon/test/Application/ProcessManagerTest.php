<?php
namespace LinuxDir\VagrantSync\Test\Application;

use PHPUnit_Framework_TestCase;
use LinuxDr\VagrantSync\Application\ProcessManager;
use System_Daemon;

require_once 'phar://' . dirname(dirname(dirname(__FILE__))) . "/build/vagrant_sync.phar/autoloader.php";
require_once "PHPUnit/Autoload.php";

class ProcessManagerTest_TestClass
{
	private static $mockProxy;

	public static function __callStatic($name, $arguments)
	{
		return self::$mockProxy->staticMethodCalled($name, $arguments);
	}

	public static function setMockProxy($object)
	{
		self::$mockProxy = $object;
	}
}

class ProcessManagerTest extends PHPUnit_Framework_TestCase
{
	public function assertPreConditions()
	{
		$this->assertTrue(class_exists('\\LinuxDr\\VagrantSync\\Application\\ProcessManager'), "Test file loading");
	}

	public function testLiveDaemonClassSetup()
	{
		$testObj = new ProcessManager();
		$this->assertEquals('System_Daemon', $testObj->getDaemonClass(), 'correct live class name');
		$this->assertEquals('vagrant_sync', $testObj->getAppName(), 'correct app name');
		$this->assertEquals('/tmp/vagrant_sync', $testObj->getAppRunDir(), 'correct app run dir');
		$this->assertEquals('/tmp/vagrant_sync/vagrant_sync.pid', $testObj->getAppPidFile(), 'correct app pid file');
		System_Daemon::setOption('appName', 'VALUE NOT SET');
		$this->assertEquals('VALUE NOT SET', $testObj->daemonGetOption('appName'), 'from class being tested');
		$this->assertEquals('VALUE NOT SET', System_Daemon::getOption('appName'), 'from daemon class');
		$testObj->daemonSetOption('appName', 'testAppNameFoo');
		$this->assertEquals('testAppNameFoo', $testObj->daemonGetOption('appName'), 'round trip value');
		$this->assertEquals('testAppNameFoo', System_Daemon::getOption('appName'), 'got to system daemon class');
		$mock = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', array('getAppName', 'getDaemonClass'));
		$mock->expects($this->any())
			->method('getAppName')
			->will($this->returnValue('mock_app'));
		$mock->expects($this->any())
			->method('getDaemonClass')
			->will($this->returnValue('LinuxDir\\VagrantSync\\Test\\Application\\ProcessManagerTest_TestClass'));
		$this->assertEquals('/tmp/mock_app', $mock->getAppRunDir(), 'correct app run dir');
		$this->assertEquals('/tmp/mock_app/mock_app.pid', $mock->getAppPidFile(), 'correct app pid file');
		$mockProxy = $this->getMock('stdClass', array('staticMethodCalled'));
		$mockProxy->expects($this->once())
			->method('staticMethodCalled')
			->with($this->equalTo('doSomething', '123'))
			->will($this->returnValue('456'));
		ProcessManagerTest_TestClass::setMockProxy($mockProxy);
		$this->assertEquals('456', $mock->daemonDoSomething(), 'correct response');
	}
}
