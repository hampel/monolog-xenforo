<?php namespace Hampel\Monolog\Option;

use Monolog\Logger;
use XF\Option\AbstractOption;

/**
 * Rendered by FileMinimumLogLevel::renderSelect(), which both level options share.
 */
class EmailMinimumLogLevel extends AbstractOption
{
	public static function get()
	{
		$logLevel = \XF::options()->monologEmailMinimumLogLevel;
		if (empty($logLevel)) $logLevel = Logger::ERROR;

		return $logLevel;
	}
}
