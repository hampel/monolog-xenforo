<?php namespace Monolog\Option;

use XF\Option\AbstractOption;

class LogFile extends AbstractOption
{
	public static function get()
	{
		return \XF::options()->monologLogFile;
	}
}
