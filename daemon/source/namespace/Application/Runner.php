<?php
namespace LinuxDr\VagrantSync\Application;

use Exception;

class InvalidArgumentException extends Exception
{
}

class Runner
{
	private $parsedArgs;

	public function __construct($argList)
	{
		$this->parseArguments($argList);
	}

	private function parseArguments($argList)
	{
		$this->parsedArgs = array('valid' => (count($argList) <= 2), 'executable' => $argList[0]);
		$validSwitches = array('kill', 'restart', 'help', 'forground');
		$validShortSwitches = array_map('chr', array_map('ord', $validSwitches));
		$optionIndex = null;
		if ((count($argList) == 2) && $this->parsedArgs['valid']) {
			$longOption = (substr($argList[1], 0, 2) == '--');
			$shortOption = (substr($argList[1], 0, 1) == '-') && (strlen($argList[1]) == 2) && !$longOption;
			$this->parsedArgs['valid'] = $longOption  || $shortOption;
			if ($this->parsedArgs['valid']) {
				$option = substr($argList[1], ($longOption ? 2 : 1));
				$optionIndex = array_search($option, ($longOption ? $validSwitches : $validShortSwitches));
				$optionIndex = (($optionIndex === false) ? null : $optionIndex);
				$this->parsedArgs['valid'] = ($optionIndex !== null);
			}
		}
		if ($this->parsedArgs['valid']) {
			for ($i = 0; $i < count($validSwitches); $i++) {
				$this->parsedArgs[$validSwitches[$i]] = ($i === $optionIndex);
			}
		}
	}

	public function launch()
	{
	}

	public function getOption($name)
	{
		if (!array_key_exists($name, $this->parsedArgs)) {
			throw new InvalidArgumentException("Option $name not defined!");
		}
		return $this->parsedArgs[$name];
	}
}
