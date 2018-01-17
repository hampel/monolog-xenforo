<?php namespace Monolog\Option;

class AddWebExtra
{
	public static function get()
	{
		return \XF::options()->monologAddWebExtra;
	}
}
