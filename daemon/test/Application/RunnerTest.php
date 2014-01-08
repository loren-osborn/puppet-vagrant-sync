<?php
namespace LinuxDir\VagrantSync\Test\Application;

use PHPUnit_Framework_TestCase;
use Exception;
use LinuxDr\VagrantSync\Application\Runner;
use LinuxDr\VagrantSync\Application\ArgParser;
use LinuxDr\VagrantSync\Exception\InvalidArgumentException;
use LinuxDr\VagrantSync\Exception\InternalErrorException;

require_once 'phar://' . dirname(dirname(dirname(__FILE__))) . "/build/vagrant_sync.phar/autoloader.php";
require_once "PHPUnit/Autoload.php";

class RunnerTest_DummyArgParser
{
	private static $mockProxy;

	public function __construct() {
		self::$mockProxy->constructorCalled(func_get_args());
	}

	public static function setMockProxy($object)
	{
		self::$mockProxy = $object;
	}
}

class RunnerTest extends PHPUnit_Framework_TestCase
{
	public function assertPreConditions()
	{
		$this->assertTrue(class_exists('LinuxDr\\VagrantSync\\Application\\Runner'), "Test file loading");
	}

	public function getExpectedStartupBehaviorsWithoutSideEffects()
	{
		$archivePath =
			dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR .
			'build' . DIRECTORY_SEPARATOR .
			'vagrant_sync.phar';
		$argParserTest = new ArgParserTest();
		$inputDataSet = $argParserTest->getExpectedStartupBehavior();
		$retVal = array();
		foreach ($inputDataSet as $internalDataItem) {
			if (
				$internalDataItem['output']['shouldTerminatOnStartup'] &&
				!$internalDataItem['output']['shouldKillBackgroundProcess']
			) {
				$externalDataItem = array();
				$externalDataItem['command'] = implode(
					' ',
					array_merge(
						array($archivePath),
						array_slice($internalDataItem['input'], 1)
					)
				);
				$externalDataItem['startupMessage'] = str_replace(
					$internalDataItem['input'][0],
					$archivePath,
					$internalDataItem['output']['getStartupMessage']
				);
				$externalDataItem['exitCode'] = $internalDataItem['output']['getExitCode'];
				$retVal[] = $externalDataItem;
			}
		}
		return $retVal;
	}

	/**
	* @dataProvider getExpectedStartupBehaviorsWithoutSideEffects
	*/
	public function testActualNonDestructiveStartup($command, $startupMessage, $exitCode)
	{
		$handle = popen($command, 'r');
		$output = fread($handle, strlen($startupMessage) + 1024);
		$status = pclose($handle);
		$this->assertEquals($startupMessage, $output, "expected output '$startupMessage' was actually '$startupMessage'");
		$this->assertEquals($exitCode, $status, "expected exit code '$exitCode' was actually '$status'");
		$proxyDummyArgParser = $this->getMock('stdClass', array('constructorCalled'));
		$proxyDummyArgParser->expects($this->once())
			->method('constructorCalled')
			->with($this->equalTo(array(array('joker', 'penguin', 'twoface'))));
		RunnerTest_DummyArgParser::setMockProxy($proxyDummyArgParser);
		$runnerTestDouble = $this->getMock('LinuxDr\\VagrantSync\\Application\\Runner', array('getClassName'));
		$runnerTestDouble->expects($this->once())
			->method('getClassName')
			->with($this->equalTo('ArgParser'))
			->will($this->returnValue('LinuxDir\\VagrantSync\\Test\\Application\\RunnerTest_DummyArgParser'));
		$this->assertTrue($runnerTestDouble->getArgParser(array('joker', 'penguin', 'twoface')) instanceof RunnerTest_DummyArgParser, "result of correct class");
	}

	public function getBadDynamicClassNames()
	{
		return array(
			array('Foo'),
			array('Bar'),
		);
	}

	public function testDynamicArgParserClass()
	{
		$testRunner = new Runner();
		try {
			$testRunner->launch();
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Arguments must be parsed first', $expected->getMessage(), "expected exception");
		}
		$this->assertEquals('baz', $testRunner->getNewArgParser(array('baz'))->getOption('executable'), "argument passed");
		$this->assertEquals('mo bile', $testRunner->getNewArgParser(array('bat', 'mo', 'bile'))->getOption('arg_list'), "arguments passed");
	}

	/**
	* @dataProvider getBadDynamicClassNames
	*/
	public function testBadDynamicClassNames($badName)
	{
		$testRunner = new Runner();
		try {
			$testRunner->getClassName($badName);
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Unknown name ' . $badName, $expected->getMessage(), "expected exception");
		}
		try {
			$methodName = 'getNew' . $badName;
			$testRunner->$methodName();
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Unknown name ' . $badName, $expected->getMessage(), "expected exception");
		}
		try {
			$methodName = strtolower($badName);
			$testRunner->$methodName('bar');
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Call to undefined method LinuxDr\VagrantSync\Application\Runner::' . $methodName . '()', $expected->getMessage(), "expected exception");
		}
	}

	public function getDynamicClassMap()
	{
		return array(
			array('ArgParser', 'LinuxDr\\VagrantSync\\Application\\ArgParser', array(array('a'))),
			array('ProcessManager', 'LinuxDr\\VagrantSync\\Application\\ProcessManager', array())
		);
	}

	/**
	* @dataProvider getDynamicClassMap
	*/
	public function testDynamicClasses($shortName, $completeClassName, $instantiationArgs)
	{
		$testRunner = new Runner();
		$this->assertEquals($completeClassName, $testRunner->getClassName($shortName), "class name mapper");
		$newInstance = call_user_func_array(array($testRunner, 'getNew' . $shortName), $instantiationArgs );
		$this->assertEquals($completeClassName, get_class($newInstance), "result of correct class");
	}

	public function mockAccessors($className, $accessorValues, $constructorArgs = array(), $extraMockMethods = array())
	{
		$mock = $this->getMock($className, array_merge(array_keys($accessorValues), $extraMockMethods), $constructorArgs);
		foreach ($accessorValues as $method => $value) {
			$mock->expects($this->any())
				->method($method)
				->will($this->returnValue($value));
		}
		return $mock;
	}

	public function mockRunnerForProcessManager($argHash, $processManager, $mainLoop)
	{
		$mockArgs = $this->mockAccessors('LinuxDr\\VagrantSync\\Application\\ArgParser', $argHash, array(array('a')));
		$mockRunner = $this->mockAccessors(
			'LinuxDr\\VagrantSync\\Application\\Runner',
				array(
					'isObjectInitialized' => true,
					'getArgParser' => $mockArgs,
					'getProcessManager' => $processManager,
					'getNewMainLoop' => $mainLoop),
				array(),
				array('echoString', 'exitWithStatus'));
		$this->assertEquals($mockArgs, $mockRunner->getArgParser(array('a')), 'test accessor');
		$this->assertEquals($processManager, $mockRunner->getProcessManager(), 'test accessor');
		return $mockRunner;
	}

	public function testCleanExit()
	{
		$procMgrMethods = array('cleanup', 'killRunningProcess', 'launchInForeground', 'launchInBackground');
		$mockProcMgr = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', $procMgrMethods);
		foreach ($procMgrMethods as $method) {
			$mockProcMgr->expects($this->never())->method($method);
		}
		$mockLoop = $this->getMock('LinuxDr\\VagrantSync\\Application\\MainLoop', array('start'));
		$mockLoop->expects($this->never())->method('start');
		$mockRunner = $this->mockRunnerForProcessManager(
			array(
				'shouldKillBackgroundProcess' => false,
				'shouldTerminatOnStartup' => true,
				'getStartupMessage' => 'red',
				'getExitCode' => 5
			),
			$mockProcMgr,
			$mockLoop);
		$mockRunner->expects($this->once())
			->method('echoString')
			->with($this->equalTo('red'));
		$mockRunner->expects($this->once())
			->method('exitWithStatus')
			->with($this->equalTo(5))
			->will($this->throwException(new Exception('Exit with status 5')));
		try {
			$mockRunner->launch();
			$this->fail("exitWithStatus should have thrown exception");
		}
		catch (Exception $expected) {
			$this->assertEquals('Exit with status 5', $expected->getMessage(), "expected exception");
		}
	}

	public function getKillPermutations()
	{
		return array(
			array('require' => false, 'exitStatus' => 6, 'outStr' => 'green'),
			array('require' => true, 'exitStatus' => 7, 'outStr' => 'blue'));
	}

	/**
	* @dataProvider getKillPermutations
	*/
	public function testKillRunningProcess($require, $exitStatus, $outStr)
	{
		$procMgrMethods = array('cleanup', 'killRunningProcess', 'launchInForeground', 'launchInBackground');
		$mockProcMgr = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', $procMgrMethods);
		$mockProcMgr->expects($this->never())->method('launchInForeground');
		$mockProcMgr->expects($this->never())->method('launchInBackground');
		$mockProcMgr->expects($this->once())->method('cleanup');
		$mockProcMgr->expects($this->once())
			->method('killRunningProcess')
			->with($this->equalTo($require));
		$mockLoop = $this->getMock('LinuxDr\\VagrantSync\\Application\\MainLoop', array('start'));
		$mockLoop->expects($this->never())->method('start');
		$mockRunner = $this->mockRunnerForProcessManager(
			array(
				'shouldKillBackgroundProcess' => true,
				'requireProcessToKill' => $require,
				'shouldTerminatOnStartup' => true,
				'getStartupMessage' => $outStr,
				'getExitCode' => $exitStatus
			),
			$mockProcMgr,
			$mockLoop);
		$mockRunner->expects($this->once())
			->method('echoString')
			->with($this->equalTo($outStr));
		$mockRunner->expects($this->once())
			->method('exitWithStatus')
			->with($this->equalTo($exitStatus))
			->will($this->throwException(new Exception('Exit with status ' . $exitStatus)));
		try {
			$mockRunner->launch();
			$this->fail("exitWithStatus should have thrown exception");
		}
		catch (Exception $expected) {
			$this->assertEquals('Exit with status ' . $exitStatus, $expected->getMessage(), "expected exception");
		}
	}

	public function getLaunchPermutations()
	{
		$retVal = array();
		$launchPermutations = array(
			array('foreground' => false, 'require' => 'launchInBackground', 'prohibit' => 'launchInForeground', 'text' => 'Ooo'),
			array('foreground' => true, 'require' => 'launchInForeground', 'prohibit' => 'launchInBackground', 'text' => 'Aaa'));
		$killPermutations = array(
			array('killRunning' => false, 'requireProcessToKill' => null),
			array('killRunning' => true, 'requireProcessToKill' => false),
			array('killRunning' => true, 'requireProcessToKill' => true));
		foreach ($launchPermutations as $launchPerm) {
			foreach ($killPermutations as $killPerm) {
				$retVal[] = array(array_merge($launchPerm, $killPerm));
			}
		}
		return $retVal;
	}

	/**
	* @dataProvider getLaunchPermutations
	*/
	public function testLaunchPermutations($config)
	{
		$procMgrMethods = array('cleanup', 'killRunningProcess', 'launchInForeground', 'launchInBackground');
		$mockProcMgr = $this->getMock('LinuxDr\\VagrantSync\\Application\\ProcessManager', $procMgrMethods);
		$mockProcMgr->expects($this->never())
			->method($config['prohibit']);
		$mockProcMgr->expects($this->once())
			->method($config['require']);
		$mockProcMgr->expects($this->once())
			->method('cleanup');
		if ($config['killRunning']) {
			$mockProcMgr->expects($this->once())
				->method('killRunningProcess')
				->with($this->equalTo($config['requireProcessToKill']));
		} else {
			$mockProcMgr->expects($this->never())->method('killRunningProcess');
		}
		$mockLoop = $this->getMock('LinuxDr\\VagrantSync\\Application\\MainLoop', array('start'));
		$mockRunner = $this->mockRunnerForProcessManager(
			array(
				'shouldKillBackgroundProcess' => $config['killRunning'],
				'requireProcessToKill' => $config['requireProcessToKill'],
				'shouldTerminatOnStartup' => false,
				'shouldStartInForeground' => $config['foreground'],
				'getStartupMessage' => $config['text']
			),
			$mockProcMgr,
			$mockLoop);
		$mockRunner->expects($this->once())
			->method('echoString')
			->with($this->equalTo($config['text']));
		$mockRunner->expects($this->never())
			->method('exitWithStatus');
		$mockLoop->expects($this->once())
			->method('start')
			->with($this->equalTo($mockRunner))
			->will($this->throwException(new Exception('MainLoop::start() called')));
		try {
			$mockRunner->launch();
			$this->fail("MainLoop::start should have thrown exception");
		}
		catch (Exception $expected) {
			$this->assertEquals('MainLoop::start() called', $expected->getMessage(), "expected exception");
		}
	}
}
