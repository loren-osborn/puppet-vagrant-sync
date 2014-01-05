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
		if ($shortName != 'ArgParser') {
			throw new InternalErrorException("Unknown name $shortName");
		}
		return 'LinuxDr\\VagrantSync\\Application\\ArgParser';
	}

	public function __call($name, $arguments)
	{
		$matches = array();
		if (preg_match('/^getNew(.*)$/', $name, $matches)) {
			$className = $this->getClassName($matches[1]);
			if ($className === '') {
				throw new \Exception("Expected non-empty class-name for short name: {$matches[1]}");
			}
			try {
				$classObj = new ReflectionClass($className);
			}
			catch (\Exception $e) {
				throw new \Exception("got exception for method $name (class shortname {$matches[1]} / Full name {$className}): " . $e->getMessage());
			}
			return $classObj->newInstanceArgs($arguments);
		}
		throw new InternalErrorException('Call to undefined method ' . __CLASS__ . "::{$name}()");
	}

	public function launch()
	{
		echo $this->argParser->getStartupMessage();
		exit ($this->argParser->getExitCode());
	}
}
