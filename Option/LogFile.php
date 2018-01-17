<?php namespace Monolog\Option;

use XF\Option\AbstractOption;

class LogFile extends AbstractOption
{
	public static function isEnabled()
	{
		return \XF::options()->monologLogFile['enabled'] !== false;
	}


	public static function getLogFile()
	{
		if (!self::isEnabled()) return '';

		return \XF::options()->monologLogFile['logfile'];
	}
}
