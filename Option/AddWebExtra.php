<?php namespace Monolog\Option;

use XF\Option\AbstractOption;

class AddWebExtra extends AbstractOption
{
	public static function get()
	{
		return \XF::options()->monologAddWebExtra;
	}
}
