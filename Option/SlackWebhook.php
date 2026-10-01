<?php namespace Hampel\Monolog\Option;

use Hampel\Monolog\Config;
use XF\Entity\Option;

/**
 * Whether to post log records to Slack, and the incoming webhook to post to - `slack` in
 * config.php can set both.
 *
 * The webhook URL is a credential: anyone holding it can post to the channel. So it is never
 * shown once config.php sets it - the locked option and monolog:config say only that it is set.
 */
class SlackWebhook extends AbstractConfigurableOption
{
	public static function isEnabled(): bool
	{
		$enabled = Config::enabled('slack') ?? !empty(\XF::options()->monologSlack['enabled']);

		return $enabled && self::getWebhook() !== '';
	}

	public static function getWebhook(): string
	{
		$webhook = Config::string('slack', 'webhook') ?? (string) (\XF::options()->monologSlack['webhook'] ?? '');

		return self::isWebhookUrl($webhook) ? $webhook : '';
	}

	public static function lockedValue(): ?string
	{
		$enabled = Config::enabled('slack');
		if ($enabled === false)
		{
			return (string) \XF::phrase('disabled');
		}

		return $enabled ? (string) \XF::phrase('enabled') : null;
	}

	/**
	 * An https URL - Slack's webhooks are on hooks.slack.com, but a proxy or a Slack-compatible
	 * service (Mattermost, Rocket.Chat) is fine too.
	 */
	public static function isWebhookUrl(string $url): bool
	{
		return filter_var($url, FILTER_VALIDATE_URL) !== false
			&& strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
	}

	protected static function verifyValue(&$value, Option $option): bool
	{
		$webhook = trim((string) ($value['webhook'] ?? ''));
		$value['webhook'] = $webhook;

		if (!empty($value['enabled']) && !self::isWebhookUrl($webhook))
		{
			$option->error(\XF::phrase('monolog_slack_webhook_must_be_https_url'), $option->option_id);
			return false;
		}

		return true;
	}

	protected static function renderControl(Option $option, array $htmlParams): string
	{
		return static::renderOnOffTextBox(
			$option, $htmlParams, 'enabled', 'webhook', '', (string) \XF::phrase('monolog_enter_slack_webhook_url')
		);
	}
}
