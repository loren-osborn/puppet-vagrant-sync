<?php
namespace LinuxDir\VagrantSync\Test\Application;

use PHPUnit_Framework_TestCase;
use Exception;
use LinuxDr\VagrantSync\Application\Runner;
use LinuxDr\VagrantSync\Application\InvalidArgumentException;

require_once "PHPUnit/Autoload.php";
require_once 'phar://' . dirname(dirname(dirname(__FILE__))) . "/build/vagrant_sync.phar/namespace/Application/Runner.php";

class RunnerTest extends PHPUnit_Framework_TestCase
{
	public function assertPreConditions()
	{
		$this->assertTrue(class_exists('\\LinuxDr\\VagrantSync\\Application\\Runner'), "Test file loading");
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
		$testRunner = new Runner($input);
		foreach ($output as $k => $v) {
			$this->assertSame($v, $testRunner->getOption($k), "expected value of $k to be $v but is actually " . $testRunner->getOption($k));
		}
		foreach ($invalidKeys as $invalid) {
			try {
				$testRunner->getOption($invalid);
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
				'output' => array('startup_message' => '', 'terminate_at_startup' => false, 'exit_status' => null)),
			array(
				'input' => array('bar', '--kill'),
				'output' => array('startup_message' => '', 'terminate_at_startup' => false, 'exit_status' => null)),
			array(
				'input' => array('baz', '--restart'),
				'output' => array('startup_message' => '', 'terminate_at_startup' => false, 'exit_status' => null)),
			array(
				'input' => array('bah', '--foreground'),
				'output' => array('startup_message' => '', 'terminate_at_startup' => false, 'exit_status' => null)),
			// Display help, no error
			array(
				'input' => array('apple', '--help'),
				'output' => array('startup_message' => join('apple', $helpMessageParts), 'terminate_at_startup' => true, 'exit_status' => 0)),
			array(
				'input' => array('banana', '-h'),
				'output' => array('startup_message' => join('banana', $helpMessageParts), 'terminate_at_startup' => true, 'exit_status' => 0)),
			// Error condition, display help
			array(
				'input' => array('carrot', '--invalid'),
				'output' => array(
					'startup_message' =>
						$beforeBadArgs . '--invalid' . $afterBadArgs .
						join('carrot', $helpMessageParts),
					'terminate_at_startup' => true,
					'exit_status' => 1)),
			array(
				'input' => array('dinosaur', 'doc', 'dopey', 'sneezy', 'sleepy', 'grumpy', 'bashful', 'happy'),
				'output' => array(
					'startup_message' =>
						$beforeBadArgs . 'doc dopey sneezy sleepy grumpy bashful happy' . $afterBadArgs .
						join('dinosaur', $helpMessageParts),
					'terminate_at_startup' => true,
					'exit_status' => 1))
		);
	}

	/**
	* @dataProvider getExpectedStartupBehavior
	*/
	public function testStartupBehavior($input, $output)
	{
		$testRunner = new Runner($input);
		$this->assertEquals($output['startup_message'], $testRunner->getStartupMessage(), "expected startup message");
		$this->assertSame($output['terminate_at_startup'], $testRunner->shouldTerminatOnStartup(), "should terminate?");
		$this->assertSame($output['exit_status'], $testRunner->getExitCode(), "exit status?");
	}
}
