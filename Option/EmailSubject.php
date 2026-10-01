<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * The email subject, with `{board}` replaced by the board title - `email.subject` in config.php
 * can set it.
 */
class EmailSubject extends AbstractConfigurableOption
{
	protected const DEPENDS_ON = 'email';

	public static function get()
	{
		$subject = Config::string('email', 'subject') ?? \XF::options()->monologEmailSubject;
		if (empty($subject)) return "Monolog";

		$tokens = [
			'{board}' => \XF::options()->boardTitle,
		];
		return strtr($subject, $tokens);
	}

	public static function lockedValue(): ?string
	{
		return Config::string('email', 'subject');
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderTextBox($option, $htmlParams);
	}
}
