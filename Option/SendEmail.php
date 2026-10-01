<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * Whether to email log records, and to whom - `email` in config.php can set both.
 */
class SendEmail extends AbstractConfigurableOption
{
	public static function isEnabled()
	{
		return Config::enabled('email') ?? !empty(\XF::options()->monologSendEmail['enabled']);
	}

	public static function getAddress()
	{
		if (!self::isEnabled()) return '';

		$email = Config::string('email', 'to') ?? (\XF::options()->monologSendEmail['email'] ?? '');

		return empty($email) ? \XF::options()->contactEmailAddress : $email;
	}

	public static function lockedValue(): ?string
	{
		$enabled = Config::enabled('email');
		if ($enabled === false)
		{
			return (string) \XF::phrase('disabled');
		}

		return Config::string('email', 'to') ?? ($enabled ? (string) \XF::phrase('enabled') : null);
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderOnOffTextBox(
			$option, $htmlParams, 'enabled', 'email', '', (string) \XF::phrase('monolog_enter_recipient_email')
		);
	}
}
