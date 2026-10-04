<?php namespace Hampel\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\MailHandler;
use Monolog\Handler\Slack\SlackRecord;
use Monolog\Logger;

/**
 * Posts log records to a Slack incoming webhook through XenForo's HTTP client.
 *
 * Monolog's own SlackWebhookHandler posts with raw cURL and no timeout, retries a failure five
 * times and then throws - so on a web request, a slow Slack hangs the page and an unreachable one
 * breaks whatever add-on was logging. This posts once, with a short timeout, and a failure goes to
 * XenForo's server error log instead of the caller. The message itself is Monolog's: SlackRecord
 * builds each record's attachment, the same on Monolog 2 and 3.
 *
 * It extends MailHandler for the batch: behind a DeduplicatingHandler, a request's records arrive
 * together and go out as one message - send() is the only method a subclass writes.
 */
class XenForoSlackHandler extends MailHandler
{
	/** The most records one message carries; Slack truncates a long one anyway. */
	public const MAX_ATTACHMENTS = 10;

	/** Whether a post is in progress, across every instance - see XenForoMailHandler. */
	private static bool $sending = false;

	private string $webhook;

	private string $site;

	private SlackRecord $slackRecord;

	/**
	 * @param int|string $level
	 */
	public function __construct(string $webhook, string $site, $level = Logger::ERROR, bool $bubble = true)
	{
		parent::__construct($level, $bubble);

		$this->webhook = $webhook;
		$this->site = $site;
		// short attachments, with context and extra as fields - the request id among them
		$this->slackRecord = new SlackRecord(null, null, true, null, true, true);
	}

	protected function send(string $content, array $records): void
	{
		if (self::$sending || !$records)
		{
			return;
		}

		self::$sending = true;

		try
		{
			\XF::app()->http()->client()->post($this->webhook, [
				'json' => $this->payload($records),
				'timeout' => 5,
				'connect_timeout' => 3,
			]);
		}
		catch (\Throwable $e)
		{
			// Guzzle puts the URL in its messages, and the URL is the credential
			\XF::logError('Monolog: posting to Slack failed - '
				. str_replace($this->webhook, '[webhook]', get_class($e) . ': ' . $e->getMessage()));
		}
		finally
		{
			self::$sending = false;
		}
	}

	/**
	 * One message for the batch: a line naming the site and the count, then an attachment per
	 * record, up to MAX_ATTACHMENTS.
	 *
	 * @param array $records Monolog 2 arrays or Monolog 3 LogRecords - SlackRecord takes either
	 */
	public function payload(array $records): array
	{
		$attachments = [];
		foreach (array_slice($records, 0, self::MAX_ATTACHMENTS) AS $record)
		{
			$data = $this->slackRecord->getSlackData($record);
			$attachments = array_merge($attachments, $data['attachments'] ?? []);
		}

		$count = count($records);
		$more = $count - self::MAX_ATTACHMENTS;
		if ($more > 0)
		{
			$attachments[] = ['text' => "and {$more} more - see the log file"];
		}

		return [
			'text' => $count === 1 ? "1 log record from {$this->site}" : "{$count} log records from {$this->site}",
			'attachments' => $attachments,
		];
	}

	/**
	 * Formatting is SlackRecord's, in send(); MailHandler formats the batch regardless, so give it
	 * the cheapest formatter rather than its HTML one.
	 */
	protected function getDefaultFormatter(): FormatterInterface
	{
		return new LineFormatter();
	}
}
