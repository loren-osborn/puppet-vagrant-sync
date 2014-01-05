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
		$this->assertTrue(class_exists('\\LinuxDr\\VagrantSync\\Application\\Runner'), "Test file loading");
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
	}

	public function testDynamicArgParserClass()
	{
		$testRunner = new Runner(array('a'));
		$this->assertEquals('LinuxDr\\VagrantSync\\Application\\ArgParser', $testRunner->getClassName('ArgParser'), "class name mapper");
		try {
			$testRunner->getClassName('Foo');
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Unknown name Foo', $expected->getMessage(), "expected exception");
		}
		try {
			$testRunner->getClassName('Bar');
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Unknown name Bar', $expected->getMessage(), "expected exception");
		}
		try {
			$testRunner->getNewFoo();
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Unknown name Foo', $expected->getMessage(), "expected exception");
		}
		$this->assertTrue($testRunner->getNewArgParser(array('a')) instanceof ArgParser, "result of correct class");
		$this->assertEquals('baz', $testRunner->getNewArgParser(array('baz'))->getOption('executable'), "argument passed");
		$this->assertEquals('mo bile', $testRunner->getNewArgParser(array('bat', 'mo', 'bile'))->getOption('arg_list'), "arguments passed");
		try {
			$testRunner->foo('bar');
			$this->fail("InternalErrorException expected");
		}
		catch (InternalErrorException $expected) {
			$this->assertEquals('Internal Error: Call to undefined method LinuxDr\VagrantSync\Application\Runner::foo()', $expected->getMessage(), "expected exception");
		}
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
		$this->assertTrue($runnerTestDouble->getNewArgParser(array('joker', 'penguin', 'twoface')) instanceof RunnerTest_DummyArgParser, "result of correct class");
	}
}
