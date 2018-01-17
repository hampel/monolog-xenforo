<?php namespace Monolog\Option;

use XF\Option\AbstractOption;

class SendEmail extends AbstractOption
{
	public static function isEnabled()
	{
		return \XF::options()->monologSendEmail['enabled'] !== false;
	}

	public static function getAddress()
	{
		if (!self::isEnabled()) return '';

		$email = \XF::options()->monologSendEmail['email'];
		if (empty($email)) return \XF::options()->contactEmailAddress;
		return $email;
	}
}
