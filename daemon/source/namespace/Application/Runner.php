<?php
namespace LinuxDr\VagrantSync\Application;

use Exception;

class InvalidArgumentException extends Exception
{
}

class InternalErrorException extends Exception
{
	public function __construct($message, $code = 0, $previous = null)
	{
		parent::__construct('Internal Error: ' . $message, $code, $previous);
	}
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
		$this->parsedArgs = array(
			'valid' => (count($argList) <= 2),
			'executable' => $argList[0],
			'arg_list' => join(' ', array_slice($argList, 1)));
		$validSwitches = array('help', 'kill', 'restart', 'foreground');
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
		echo $this->getStartupMessage();
		exit ($this->getExitCode());
	}

	public function getOption($name)
	{
		if (!array_key_exists($name, $this->parsedArgs)) {
			throw new InvalidArgumentException("Option $name not defined!");
		}
		return $this->parsedArgs[$name];
	}

	public function getStartupMessage()
	{
		$retVal = '';
		$progName = $this->getOption('executable');
		if ( (!$this->getOption('valid')) || $this->getOption('help') ) {
			$helpMessage = <<<HELP_MESSAGE_EOF
{$progName}  --  keep vagrant shared directory shared to local mirror

   -h | --help        Display this help message
   -k | --kill        Terminate an instance of {$progName} already running
   -r | --restart     Terminate and restart {$progName}
   -f | --foreground  Run in foreground. (Do not run as a deamon)


HELP_MESSAGE_EOF;
			if (!$this->getOption('valid')) {
				$helpMessage =
					"Unrecognized arguments: " . $this->getOption('arg_list') . "\n\n" .
					$helpMessage;
			}
			$retVal = $helpMessage;
		}
		return $retVal;
	}

	public function shouldTerminatOnStartup()
	{
		return (!$this->getOption('valid')) || $this->getOption('help') || $this->getOption('kill');
	}

	public function getExitCode()
	{
		if (!$this->shouldTerminatOnStartup()) {
			throw new InternalErrorException("only valid if terminating on startup");
		}
		return ($this->getOption('valid') ? 0 : 1);
	}

	public function shouldKillBackgroundProcess()
	{
		return $this->getOption('valid') && (($this->getOption('kill')) || $this->getOption('restart'));
	}

	public function shouldStartInForeground()
	{
		if ($this->shouldTerminatOnStartup()) {
			throw new InternalErrorException("only valid if not terminating on startup");
		}
		return $this->getOption('foreground');
	}

	public function requireProcessToKill()
	{
		if (!$this->shouldKillBackgroundProcess()) {
			throw new InternalErrorException("only valid if background process should be killed");
		}
		return $this->getOption('kill');
	}
}
