<?php namespace Hampel\Monolog\Option;

use XF\Option\AbstractOption;

class SendEmail extends AbstractOption
{
	public static function isEnabled()
	{
		return !empty(\XF::options()->monologSendEmail['enabled']);
	}

	public static function getAddress()
	{
		if (!self::isEnabled()) return '';

		$email = \XF::options()->monologSendEmail['email'];
		if (empty($email)) return \XF::options()->contactEmailAddress;
		return $email;
	}
}
