<?php namespace Hampel\Monolog\Option;

use XF\Option\AbstractOption;

class AddVisitorExtra extends AbstractOption
{
	public static function get()
	{
		return \XF::options()->monologAddVisitorExtra;
	}
}
