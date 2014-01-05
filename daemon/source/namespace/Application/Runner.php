<?php
namespace LinuxDr\VagrantSync\Application;

use Exception;

class Runner
{
	private $argParser;

	public function __construct($argList)
	{
		$this->argParser = new ArgParser($argList);
	}

	public function launch()
	{
		echo $this->argParser->getStartupMessage();
		exit ($this->argParser->getExitCode());
	}
}
