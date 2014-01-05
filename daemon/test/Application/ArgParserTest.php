<?php
namespace LinuxDir\VagrantSync\Test\Application;

use PHPUnit_Framework_TestCase;
use Exception;
use LinuxDr\VagrantSync\Application\ArgParser;
use LinuxDr\VagrantSync\Exception\InvalidArgumentException;
use LinuxDr\VagrantSync\Exception\InternalErrorException;

require_once 'phar://' . dirname(dirname(dirname(__FILE__))) . "/build/vagrant_sync.phar/autoloader.php";
require_once "PHPUnit/Autoload.php";

class ArgParserTest extends PHPUnit_Framework_TestCase
{
	public function assertPreConditions()
	{
		$this->assertTrue(class_exists('\\LinuxDr\\VagrantSync\\Application\\ArgParser'), "Test file loading");
	}

	private function generateExpectedNoArgumentPermutations($exeNames, $validOptions, $invalidOptions)
	{
		$retVal = array();
		foreach ($exeNames as $name) {
			$permutation = array(
				'input' => array($name),
				'output' => array('valid' => true, 'executable' => $name),
				'invalidKeys' => $invalidOptions
			);
			foreach ($validOptions as $option) {
				$permutation['output'][$option] = false;
			}
			$permutation['output']['arg_list'] = join(' ', array_slice($permutation['input'], 1));
			$retVal[] = $permutation;
		}
		return $retVal;
	}

	private function generateExpectedInvalidArgCountPermutations($exeNames, $validOptions, $invalidOptions)
	{
		$retVal = array();
		$exeIndex = 0;
		$shortenToggel = false;
		// No options are valid if more than one is given
		$invalidOptions = array_merge($validOptions, $invalidOptions);
		$validOptions = array(); // not used
		for ($i = 2; $i <= 8; $i++) {
			$permutation = array(
				'input' => array($exeNames[$exeIndex]),
				'output' => array('valid' => false, 'executable' => $exeNames[$exeIndex]),
				'invalidKeys' => $invalidOptions
			);
			for ($j = 0; $j < $i; $j++) {
				$switch = '--' . $invalidOptions[($i + $j) % count($invalidOptions)];
				if ($shortenToggel) {
					$switch = substr($switch, 1, 2);
				}
				$permutation['input'][] = $switch;
			}
			$permutation['output']['arg_list'] = join(' ', array_slice($permutation['input'], 1));
			$retVal[] = $permutation;
			$exeIndex = ($exeIndex + 1) % count($exeNames);
			$shortenToggel = $shortenToggel ? false : true;
		}
		return $retVal;
	}

	private function generateExpectedInvalidNamedArgsPermutations($exeNames, $validOptions, $invalidOptions)
	{
		$retVal = array();
		$exeIndex = 0;
		$shortenToggel = false;
		for ($i = 0; $i < count($invalidOptions); $i++) {
			foreach (array(false, true) as $shortenToggel) {
				$permutation = array(
					'input' => array($exeNames[$exeIndex], ('--' . $invalidOptions[$i])),
					'output' => array('valid' => false, 'executable' => $exeNames[$exeIndex]),
					'invalidKeys' => array_merge($validOptions, $invalidOptions)
				);
				if ($shortenToggel) {
					$permutation['input'][1] = substr($permutation['input'][1], 1, 2);
				}
				$permutation['output']['arg_list'] = join(' ', array_slice($permutation['input'], 1));
				$retVal[] = $permutation;
				$exeIndex = ($exeIndex + 1) % count($exeNames);
			}
		}
		return $retVal;
	}

	private function generateExpectedValidNamedArgsPermutations($exeNames, $validOptions, $invalidOptions)
	{
		$retVal = array();
		$exeIndex = 0;
		$shortenToggel = false;
		for ($i = 0; $i < count($validOptions); $i++) {
			foreach (array(false, true) as $shortenToggel) {
				$permutation = array(
					'input' => array($exeNames[$exeIndex], ('--' . $validOptions[$i])),
					'output' => array('valid' => true, 'executable' => $exeNames[$exeIndex]),
					'invalidKeys' => $invalidOptions
				);
				if ($shortenToggel) {
					$permutation['input'][1] = substr($permutation['input'][1], 1, 2);
				}
				for ($j = 0; $j < count($validOptions); $j++) {
					$permutation['output'][$validOptions[$j]] = ($i === $j);
				}
				$permutation['output']['arg_list'] = join(' ', array_slice($permutation['input'], 1));
				$retVal[] = $permutation;
				$exeIndex = ($exeIndex + 1) % count($exeNames);
			}
		}
		return $retVal;
	}

	public function generateExpectedArgumentPermutations()
	{

		$validOptions = array('kill', 'restart', 'help', 'foreground');
		$invalidOptions = array('larry', 'moe', 'curly');
		$exeNames = array('vagrant_sync.phar', 'vagrant_sync', 'foo');
		$retVal = array_merge(
			$this->generateExpectedNoArgumentPermutations($exeNames, $validOptions, $invalidOptions),
			$this->generateExpectedInvalidArgCountPermutations($exeNames, $validOptions, $invalidOptions),
			$this->generateExpectedInvalidNamedArgsPermutations($exeNames, $validOptions, $invalidOptions),
			$this->generateExpectedValidNamedArgsPermutations($exeNames, $validOptions, $invalidOptions)
		);
		return $retVal;
	}

	public function dumpGeneratedExpectedArgumentData()
	{
		$data = $this->generateExpectedArgumentPermutations();
		$nextPermSeperator = '';
		$codeSnippit = "\n\n\t\treturn array(";
		foreach ($data as $permutation) {
			$codeSnippit .= $nextPermSeperator . "\n" .
				"\t\t\tarray(\n" .
					"\t\t\t\t'input' => array('" . join("', '", $permutation['input']) . "'),\n" .
					"\t\t\t\t'output' => array(";
			$outputKeySeperator = '';
			foreach ($permutation['output'] as $k => $v) {
				$codeSnippit .= $outputKeySeperator . "'$k' => ";
				if (is_string($v)) {
					$codeSnippit .= "'$v'";
				} else {
					$codeSnippit .= ($v ? 'true' : 'false');
				}
				$outputKeySeperator = ', ';
			}
			$codeSnippit .=
					"),\n" .
					"\t\t\t\t'invalidKeys' => array('" . join("', '", $permutation['invalidKeys']) . "'))";
			$nextPermSeperator = ',';
		}
		$codeSnippit .= "\n\t\t);\n\n\n";
		$codeOutput = eval($codeSnippit);
		$this->assertEquals($data, $codeOutput, "intended value");
		echo $codeSnippit;
		exit(99);
	}

	public function getExpectedArgumentPermutations()
	{
		// $this->dumpGeneratedExpectedArgumentData();
		$retVal = array(
			array(
				'input' => array('vagrant_sync.phar'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync.phar', 'kill' => false, 'restart' => false, 'help' => false, 'foreground' => false, 'arg_list' => ''),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync', 'kill' => false, 'restart' => false, 'help' => false, 'foreground' => false, 'arg_list' => ''),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('foo'),
				'output' => array('valid' => true, 'executable' => 'foo', 'kill' => false, 'restart' => false, 'help' => false, 'foreground' => false, 'arg_list' => ''),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '--help', '--foreground'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync.phar', 'arg_list' => '--help --foreground'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync', '-f', '-l', '-m'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync', 'arg_list' => '-f -l -m'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('foo', '--larry', '--moe', '--curly', '--kill'),
				'output' => array('valid' => false, 'executable' => 'foo', 'arg_list' => '--larry --moe --curly --kill'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '-m', '-c', '-k', '-r', '-h'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync.phar', 'arg_list' => '-m -c -k -r -h'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync', '--curly', '--kill', '--restart', '--help', '--foreground', '--larry'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync', 'arg_list' => '--curly --kill --restart --help --foreground --larry'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('foo', '-k', '-r', '-h', '-f', '-l', '-m', '-c'),
				'output' => array('valid' => false, 'executable' => 'foo', 'arg_list' => '-k -r -h -f -l -m -c'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '--restart', '--help', '--foreground', '--larry', '--moe', '--curly', '--kill', '--restart'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync.phar', 'arg_list' => '--restart --help --foreground --larry --moe --curly --kill --restart'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '--larry'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync.phar', 'arg_list' => '--larry'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync', '-l'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync', 'arg_list' => '-l'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('foo', '--moe'),
				'output' => array('valid' => false, 'executable' => 'foo', 'arg_list' => '--moe'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '-m'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync.phar', 'arg_list' => '-m'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync', '--curly'),
				'output' => array('valid' => false, 'executable' => 'vagrant_sync', 'arg_list' => '--curly'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('foo', '-c'),
				'output' => array('valid' => false, 'executable' => 'foo', 'arg_list' => '-c'),
				'invalidKeys' => array('kill', 'restart', 'help', 'foreground', 'larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '--kill'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync.phar', 'kill' => true, 'restart' => false, 'help' => false, 'foreground' => false, 'arg_list' => '--kill'),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync', '-k'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync', 'kill' => true, 'restart' => false, 'help' => false, 'foreground' => false, 'arg_list' => '-k'),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('foo', '--restart'),
				'output' => array('valid' => true, 'executable' => 'foo', 'kill' => false, 'restart' => true, 'help' => false, 'foreground' => false, 'arg_list' => '--restart'),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '-r'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync.phar', 'kill' => false, 'restart' => true, 'help' => false, 'foreground' => false, 'arg_list' => '-r'),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync', '--help'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync', 'kill' => false, 'restart' => false, 'help' => true, 'foreground' => false, 'arg_list' => '--help'),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('foo', '-h'),
				'output' => array('valid' => true, 'executable' => 'foo', 'kill' => false, 'restart' => false, 'help' => true, 'foreground' => false, 'arg_list' => '-h'),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync.phar', '--foreground'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync.phar', 'kill' => false, 'restart' => false, 'help' => false, 'foreground' => true, 'arg_list' => '--foreground'),
				'invalidKeys' => array('larry', 'moe', 'curly')),
			array(
				'input' => array('vagrant_sync', '-f'),
				'output' => array('valid' => true, 'executable' => 'vagrant_sync', 'kill' => false, 'restart' => false, 'help' => false, 'foreground' => true, 'arg_list' => '-f'),
				'invalidKeys' => array('larry', 'moe', 'curly'))
		);
		$this->assertEquals($this->generateExpectedArgumentPermutations(), $retVal, "generated correctly");
		return $retVal;
	}

	/**
	* @dataProvider getExpectedArgumentPermutations
	*/
	public function testArgumentParsing($input, $output, $invalidKeys)
	{
		$testArgParser = new ArgParser($input);
		foreach ($output as $k => $v) {
			$this->assertSame($v, $testArgParser->getOption($k), "expected value of $k to be $v but is actually " . $testArgParser->getOption($k));
		}
		foreach ($invalidKeys as $invalid) {
			try {
				$testArgParser->getOption($invalid);
				$this->fail("Getting option $invalid should raise an exception");
			}
			catch (InvalidArgumentException $expected) {
				$this->assertEquals("Option $invalid not defined!", $expected->getMessage(), 'expected exception');
			}
		}
	}

	public function getExpectedStartupBehavior()
	{
		$beforeBadArgs = 'Unrecognized arguments: ';
		$afterBadArgs = "\n\n";
		$helpMessageParts = array(
			'',
			("  --  keep vagrant shared directory shared to local mirror\n\n".
			"   -h | --help        Display this help message\n" .
			'   -k | --kill        Terminate an instance of '),
			(" already running\n" .
			'   -r | --restart     Terminate and restart '),
			("\n" .
			"   -f | --foreground  Run in foreground. (Do not run as a deamon)\n\n")
		);
		return array(
			// Normal silent, no-exit startup
			array(
				'input' => array('foo'),
				'output' => array(
					'getStartupMessage' => '',
					'shouldKillBackgroundProcess' => false,
					'shouldTerminatOnStartup' => false,
					'shouldStartInForeground' => false),
				'invalidKeys' => array('requireProcessToKill', 'getExitCode')),
			array(
				'input' => array('bar', '--kill'),
				'output' => array(
					'getStartupMessage' => '',
					'shouldKillBackgroundProcess' => true,
					'requireProcessToKill' => true,
					'shouldTerminatOnStartup' => true,
					'getExitCode' => 0),
				'invalidKeys' => array('shouldStartInForeground')),
			array(
				'input' => array('baz', '--restart'),
				'output' => array(
					'getStartupMessage' => '',
					'shouldKillBackgroundProcess' => true,
					'requireProcessToKill' => false,
					'shouldTerminatOnStartup' => false,
					'shouldStartInForeground' => false),
				'invalidKeys' => array('getExitCode')),
			array(
				'input' => array('bah', '--foreground'),
				'output' => array(
					'getStartupMessage' => '',
					'shouldKillBackgroundProcess' => false,
					'shouldTerminatOnStartup' => false,
					'shouldStartInForeground' => true),
				'invalidKeys' => array('requireProcessToKill', 'getExitCode')),
			// Display help, no error
			array(
				'input' => array('apple', '--help'),
				'output' => array(
					'getStartupMessage' => join('apple', $helpMessageParts),
					'shouldKillBackgroundProcess' => false,
					'shouldTerminatOnStartup' => true,
					'getExitCode' => 0),
				'invalidKeys' => array('requireProcessToKill', 'shouldStartInForeground')),
			array(
				'input' => array('banana', '-h'),
				'output' => array(
					'getStartupMessage' => join('banana', $helpMessageParts),
					'shouldKillBackgroundProcess' => false,
					'shouldTerminatOnStartup' => true,
					'getExitCode' => 0),
				'invalidKeys' => array('requireProcessToKill', 'shouldStartInForeground')),
			// Error condition, display help
			array(
				'input' => array('carrot', '--invalid'),
				'output' => array(
					'getStartupMessage' =>
						$beforeBadArgs . '--invalid' . $afterBadArgs .
						join('carrot', $helpMessageParts),
					'shouldKillBackgroundProcess' => false,
					'shouldTerminatOnStartup' => true,
					'getExitCode' => 1),
				'invalidKeys' => array('requireProcessToKill', 'shouldStartInForeground')),
			array(
				'input' => array('dinosaur', 'doc', 'dopey', 'sneezy', 'sleepy', 'grumpy', 'bashful', 'happy'),
				'output' => array(
					'getStartupMessage' =>
						$beforeBadArgs . 'doc dopey sneezy sleepy grumpy bashful happy' . $afterBadArgs .
						join('dinosaur', $helpMessageParts),
					'shouldKillBackgroundProcess' => false,
					'shouldTerminatOnStartup' => true,
					'getExitCode' => 1),
				'invalidKeys' => array('requireProcessToKill', 'shouldStartInForeground'))
		);
	}

	/**
	* @dataProvider getExpectedStartupBehavior
	*/
	public function testStartupBehavior($input, $output, $invalidKeys)
	{
		// validate expected values
		$this->assertEquals($output['shouldKillBackgroundProcess'], in_array('requireProcessToKill', array_keys($output)), "validate requireProcessToKill");
		$this->assertEquals(!$output['shouldKillBackgroundProcess'], in_array('requireProcessToKill', $invalidKeys), "validate requireProcessToKill");
		$this->assertEquals($output['shouldTerminatOnStartup'], in_array('getExitCode', array_keys($output)), "validate getExitCode");
		$this->assertEquals(!$output['shouldTerminatOnStartup'], in_array('getExitCode', $invalidKeys), "validate getExitCode");
		$this->assertEquals(!$output['shouldTerminatOnStartup'], in_array('shouldStartInForeground', array_keys($output)), "validate shouldStartInForeground");
		$this->assertEquals($output['shouldTerminatOnStartup'], in_array('shouldStartInForeground', $invalidKeys), "validate shouldStartInForeground");
		// test ArgParser
		$testArgParser = new ArgParser($input);
		foreach ($output as $method => $value) {
			try {
				$this->assertEquals($value, $testArgParser->$method(), "expected value for $method should be " . var_export($value, true));
			}
			catch (Exception $unexpected) {
				$this->fail("Raised exception " . $unexpected->__toString() . " when requesting {$method}() that should have had value: " . var_export($value, true));
			}
		}
		foreach ($invalidKeys as $invalid) {
			try {
				$testArgParser->$invalid();
				$this->fail("Calling method $invalid should raise an exception");
			}
			catch (InternalErrorException $expected) {
				$this->assertTrue(true, 'expected exception');
			}
		}
	}

	public function getExpectedStartupBehaviorsWithoutSideEffects()
	{
		$archivePath =
			dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR .
			'build' . DIRECTORY_SEPARATOR .
			'vagrant_sync.phar';
		$inputDataSet = $this->getExpectedStartupBehavior();
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
