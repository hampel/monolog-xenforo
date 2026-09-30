<?php namespace Hampel\Monolog\Option;

use XF\Option\AbstractOption;

class LogFile extends AbstractOption
{
	public static function isEnabled()
	{
		return !empty(\XF::options()->monologLogFile['enabled']);
	}


	public static function getLogFile()
	{
		if (!self::isEnabled()) return '';

		return \XF::options()->monologLogFile['logfile'];
	}
}
