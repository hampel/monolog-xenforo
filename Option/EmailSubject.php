<?php namespace Monolog\Option;

use XF\Option\AbstractOption;

class EmailSubject extends AbstractOption
{
	public static function get()
	{
		$subject = \XF::options()->monologEmailSubject;
		if (empty($subject)) return "Monolog";

		$tokens = [
			'{board}' => \XF::options()->boardTitle,
		];
		return strtr($subject, $tokens);
	}
}
