<?php
namespace LinuxDr\VagrantSync\Application;

use Exception;
use LinuxDr\VagrantSync\Exception\InternalErrorException;
use ReflectionClass;

class Runner
{
	private $containedObjects;

	public function __construct()
	{
		$this->containedObjects = array();
	}

	public function getClassName($shortName)
	{
		$classNameLookup = array(
			'ArgParser' => 'LinuxDr\\VagrantSync\\Application\\ArgParser',
			'ProcessManager' => 'LinuxDr\\VagrantSync\\Application\\ProcessManager',
			'MainLoop' => 'LinuxDr\\VagrantSync\\Application\\MainLoop' );
		if (!array_key_exists($shortName, $classNameLookup)) {
			throw new InternalErrorException("Unknown name $shortName");
		}
		return $classNameLookup[$shortName];
	}

	public function isObjectInitialized($shortName)
	{
		return array_key_exists($shortName, $this->containedObjects);
	}

	public function __call($name, $arguments)
	{
		$matches = array();
		if (preg_match('/^(get(?:New)?)(.*)$/', $name, $matches)) {
			$retVal = null;
			if (($matches[1] === 'getNew') || !array_key_exists($matches[2], $this->containedObjects)) {
				$className = $this->getClassName($matches[2]);
				$classObj = new ReflectionClass($className);
				$retVal = $classObj->newInstanceArgs($arguments);
				if ($matches[1] === 'get') {
					$this->containedObjects[$matches[2]] = $retVal;
				}
			} else {
				$retVal = $this->containedObjects[$matches[2]];
			}
			return $retVal;
		}
		throw new InternalErrorException('Call to undefined method ' . __CLASS__ . "::{$name}()");
	}

	public function parseArgs($argList)
	{
		$this->getArgParser($argList);
	}

	public function launch()
	{
		if (!$this->isObjectInitialized('ArgParser')) {
			throw new InternalErrorException('Arguments must be parsed first');
		}
		$this->echoString($this->getArgParser()->getStartupMessage());
		if ($this->getArgParser()->shouldKillBackgroundProcess()) {
			$this->getProcessManager()->cleanup();
			$this->getProcessManager()->killRunningProcess($this->getArgParser()->requireProcessToKill());
		} else if (!$this->getArgParser()->shouldTerminatOnStartup()) {
			$this->getProcessManager()->cleanup();
		}
		if ($this->getArgParser()->shouldTerminatOnStartup()) {
			$this->exitWithStatus($this->getArgParser()->getExitCode());
		} else {
			if ($this->getArgParser()->shouldStartInForeground()) {
				$this->getProcessManager()->launchInForeground();
			} else {
				$this->getProcessManager()->launchInBackground();
			}
			$this->getNewMainLoop()->start($this);
		}
	}

	protected function echoString($outString)
	{
		echo $outString;
	}

	protected function exitWithStatus($status)
	{
		exit($status);
	}
}
