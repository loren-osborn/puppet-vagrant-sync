<?php
namespace LinuxDir\VagrantSync\Test\Application;

use PHPUnit_Framework_TestCase;
use Exception;
use LinuxDr\VagrantSync\Application\Runner;
use LinuxDr\VagrantSync\Application\InvalidArgumentException;
use LinuxDr\VagrantSync\Application\InternalErrorException;

require_once 'phar://' . dirname(dirname(dirname(__FILE__))) . "/build/vagrant_sync.phar/autoloader.php";
require_once "PHPUnit/Autoload.php";

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
}
