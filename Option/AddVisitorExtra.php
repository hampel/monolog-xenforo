<?php namespace Monolog\Option;

class AddVisitorExtra
{
	public static function get()
	{
		return \XF::options()->monologAddVisitorExtra;
	}
}
