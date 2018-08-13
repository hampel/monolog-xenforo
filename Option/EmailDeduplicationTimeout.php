<?php namespace Hampel\Monolog\Option;

use XF\Option\AbstractOption;

class EmailDeduplicationTimeout extends AbstractOption
{
	public static function get()
	{
		return \XF::options()->monologEmailDeduplicationTimeout;
	}
}
