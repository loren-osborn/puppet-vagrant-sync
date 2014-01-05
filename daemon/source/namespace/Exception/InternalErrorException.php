<?php
namespace LinuxDr\VagrantSync\Exception;

use Exception;

class InternalErrorException extends Exception
{
	public function __construct($message, $code = 0, $previous = null)
	{
		parent::__construct('Internal Error: ' . $message, $code, $previous);
	}
}
