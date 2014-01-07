<?php
namespace LinuxDr\VagrantSync\Application;

use Exception;
use LinuxDr\VagrantSync\Exception\InternalErrorException;
use ReflectionClass;

class Runner
{
	private $argParser;

	public function parseArgs($argList)
	{
		$this->argParser = $this->getNewArgParser($argList);
	}

	public function getClassName($shortName)
	{
		$classNameLookup = array(
			'ArgParser' => 'LinuxDr\\VagrantSync\\Application\\ArgParser',
			'ProcessManager' => 'LinuxDr\\VagrantSync\\Application\\ProcessManager' );
		if (!array_key_exists($shortName, $classNameLookup)) {
			throw new InternalErrorException("Unknown name $shortName");
		}
		return $classNameLookup[$shortName];
	}

	public function __call($name, $arguments)
	{
		$matches = array();
		if (preg_match('/^getNew(.*)$/', $name, $matches)) {
			$className = $this->getClassName($matches[1]);
			$classObj = new ReflectionClass($className);
			return $classObj->newInstanceArgs($arguments);
		}
		throw new InternalErrorException('Call to undefined method ' . __CLASS__ . "::{$name}()");
	}

	public function launch()
	{
		if (!is_object($this->argParser)) {
			throw new InternalErrorException('Arguments must be parsed first');
		}
		echo $this->argParser->getStartupMessage();
		exit ($this->argParser->getExitCode());
	}
}
