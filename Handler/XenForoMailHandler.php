<?php namespace Hampel\Monolog\Handler;

use Monolog\Handler\MailHandler;
use Monolog\Logger;
use XF\Mail\Mailer;

/**
 * Sends log records through XenForo's own mail system.
 *
 * XenForo hides its mail library behind XF\Mail\Mail - SwiftMailer on 2.2, Symfony Mailer on 2.3 -
 * so one handler serves both, and mail goes out through whatever transport the board is
 * configured for, with its default sender and return path. A failed send is XenForo's to handle:
 * it logs the exception to the server error log, and 2.2 queues a retry.
 */
class XenForoMailHandler extends MailHandler
{
	/**
	 * Whether a send is in progress, across every instance. A transport that logs its own failure
	 * at a level this handler handles would otherwise report that failure through itself.
	 */
	private static bool $sending = false;

	private Mailer $mailer;

	private string $to;

	private string $subject;

	/**
	 * @param int|string $level
	 */
	public function __construct(Mailer $mailer, string $to, string $subject, $level = Logger::ERROR, bool $bubble = true)
	{
		parent::__construct($level, $bubble);

		$this->mailer = $mailer;
		$this->to = $to;
		$this->subject = $subject;
	}

	protected function send(string $content, array $records): void
	{
		if (self::$sending)
		{
			return;
		}

		self::$sending = true;

		try
		{
			$mail = $this->mailer->newMail()->setTo($this->to);

			if ($this->isHtmlBody($content))
			{
				$mail->setContent($this->subject, $content);
			}
			else
			{
				$mail->setContent($this->subject, '<pre>' . htmlspecialchars($content) . '</pre>', $content);
			}

			$mail->send();
		}
		finally
		{
			self::$sending = false;
		}
	}
}
