<?php
namespace LinuxDir\VagrantSync\Test\Application;

use PHPUnit_Framework_TestCase;
use LinuxDr\VagrantSync\Application\ProcessManager;
use System_Daemon;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use Exception;

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
	public function isPidRunning($pid)
	{
		if (!preg_match('/^(0|[1-9]\d*)$/', ($pid . ''))) {
			throw new Exception("\$pid ({$pid}) must be a non-negative integer");
		}
		$output = null;
		$return_var = null;
		exec("ps $pid", $output, $return_var);
		return ($return_var == 0);
	}

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
		$mock = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', array('getAppRunDir'));
		$mock->expects($this->any())
			->method('getAppRunDir')
			->will($this->returnValue('/foo/bar/vagrant_sync'));
		$this->assertEquals('/foo/bar/vagrant_sync/vagrant_sync.pid', $mock->getAppPidFile(), 'correct app pid file');
	}

	public function testGetCurrentPid()
	{
		// no app dir
		$tempDir = vfsStream::setup('tempDir');
		$mock = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', array('getAppRunDir'));
		$mock->expects($this->any())
			->method('getAppRunDir')
			->will($this->returnValue(vfsStream::url('tempDir') . '/vagrant_sync'));
        $this->assertFalse($tempDir->hasChild('vagrant_sync'), 'missing directory');
		$this->assertEquals(null, $mock->getRunningPid(), 'correct response');
        $this->assertFalse($tempDir->hasChild('vagrant_sync'), 'missing directory');

		// app dir with no pid file
		$tempDir = vfsStream::setup('tempDir');
		$appDir = new vfsStreamDirectory('vagrant_sync');
		$tempDir->addChild($appDir);
		$mock = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', array('getAppRunDir'));
		$mock->expects($this->any())
			->method('getAppRunDir')
			->will($this->returnValue($appDir->url()));
        $this->assertTrue($tempDir->hasChild('vagrant_sync'), 'correct directory');
        $this->assertFalse($tempDir->getChild('vagrant_sync')->hasChild('vagrant_sync.pid'), 'correct filename');
		$this->assertEquals(null, $mock->getRunningPid(), 'correct response');
        $this->assertTrue($tempDir->hasChild('vagrant_sync'), 'correct directory');
        $this->assertFalse($tempDir->getChild('vagrant_sync')->hasChild('vagrant_sync.pid'), 'correct filename');

		// app dir with pid file for non-running pid
		$nonRunningPid = posix_getpid();
		$this->assertTrue($this->isPidRunning($nonRunningPid), 'phpunit script process should be running');
		while ($this->isPidRunning($nonRunningPid)) {
			$nonRunningPid--;
		}
		$this->assertFalse($this->isPidRunning($nonRunningPid), 'found a deceased pid');
		$tempDir = vfsStream::setup('tempDir');
		$pidFile = new vfsStreamFile('vagrant_sync.pid');
		$pidFile->setContent("{$nonRunningPid}\n");
		$appDir = new vfsStreamDirectory('vagrant_sync');
		$appDir->addChild($pidFile);
		$tempDir->addChild($appDir);
		$mock = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', array('getAppRunDir'));
		$mock->expects($this->any())
			->method('getAppRunDir')
			->will($this->returnValue($appDir->url()));
        $this->assertTrue($tempDir->hasChild('vagrant_sync'), 'correct directory');
        $this->assertTrue($tempDir->getChild('vagrant_sync')->hasChild('vagrant_sync.pid'), 'correct filename');
        $this->assertEquals("{$nonRunningPid}\n", $tempDir->getChild('vagrant_sync')->getChild('vagrant_sync.pid')->getContent(), 'correct content');
		$this->assertFalse($this->isPidRunning($nonRunningPid), 'non-running pid');
		$this->assertEquals(null, $mock->getRunningPid(), 'correct response');
        $this->assertTrue($tempDir->hasChild('vagrant_sync'), 'correct directory');
        $this->assertTrue($tempDir->getChild('vagrant_sync')->hasChild('vagrant_sync.pid'), 'correct filename');
        $this->assertEquals("{$nonRunningPid}\n", $tempDir->getChild('vagrant_sync')->getChild('vagrant_sync.pid')->getContent(), 'correct content');
		$this->assertFalse($this->isPidRunning($nonRunningPid), 'non-running pid');

		// app dir with pid file for running pid
		$tempDir = vfsStream::setup('tempDir');
		$pidFile = new vfsStreamFile('vagrant_sync.pid');
		$pidFile->setContent(posix_getpid() . "\n");
		$appDir = new vfsStreamDirectory('vagrant_sync');
		$appDir->addChild($pidFile);
		$tempDir->addChild($appDir);
		$mock = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', array('getAppRunDir'));
		$mock->expects($this->any())
			->method('getAppRunDir')
			->will($this->returnValue($appDir->url()));
        $this->assertTrue($tempDir->hasChild('vagrant_sync'), 'correct directory');
        $this->assertTrue($tempDir->getChild('vagrant_sync')->hasChild('vagrant_sync.pid'), 'correct filename');
        $this->assertEquals(posix_getpid() . "\n", $tempDir->getChild('vagrant_sync')->getChild('vagrant_sync.pid')->getContent(), 'correct content');
		$this->assertEquals(posix_getpid(), $mock->getRunningPid(), 'correct response');
        $this->assertTrue($tempDir->hasChild('vagrant_sync'), 'correct directory');
        $this->assertTrue($tempDir->getChild('vagrant_sync')->hasChild('vagrant_sync.pid'), 'correct filename');
        $this->assertEquals(posix_getpid() . "\n", $tempDir->getChild('vagrant_sync')->getChild('vagrant_sync.pid')->getContent(), 'correct content');
	}

	public function getIsAlreadyRunningPermutations()
	{
		return array(
			array('runningPid' => null, 'result' => false),
			array('runningPid' => 123, 'result' => true),
			array('runningPid' => 456, 'result' => true));
	}


	/**
	* @dataProvider getIsAlreadyRunningPermutations
	*/
	public function testIsAlreadyRunning($runningPid, $result)
	{
		$mock = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', array('getRunningPid'));
		$mock->expects($this->any())
			->method('getRunningPid')
			->will($this->returnValue($runningPid));
		$this->assertEquals($result, $mock->isAlreadyRunning(), 'correct response');
	}

	public function testCleanup()
	{
		// $procMgrMethods = array('cleanup', 'killRunningProcess', 'launchInForeground', 'launchInBackground');
	}
}
