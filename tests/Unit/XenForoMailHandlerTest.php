<?php namespace Tests\Unit;

use Hampel\Monolog\Handler\XenForoMailHandler;
use Monolog\Logger;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class XenForoMailHandlerTest extends TestCase
{
	public function test_a_record_is_sent_through_the_xenforo_mailer()
	{
		$this->fakesMail();

		$logger = new Logger('myaddon', [
			new XenForoMailHandler($this->app()->mailer(), 'logs@example.com', 'Subject', Logger::ERROR),
		]);
		$logger->error('it broke');

		$this->assertMailSent(function ($mail)
		{
			return $mail->getTo()[0]->getAddress() === 'logs@example.com'
				&& $mail->getSubject() === 'Subject'
				&& strpos((string) $mail->getHtmlBody(), 'it broke') !== false;
		});
	}

	/**
	 * Unbuffered, so nothing stands between the transport's own log record and this handler but
	 * the guard. In the default stack DeduplicatingHandler's buffer absorbs the record as well;
	 * this is the case where a handler stack is assembled without one.
	 */
	public function test_a_record_logged_by_the_transport_while_sending_is_not_sent()
	{
		$logger = new Logger('myaddon');

		$transport = new class($logger) extends AbstractTransport
		{
			public $sent = 0;
			private $logger;

			public function __construct($logger)
			{
				parent::__construct();
				$this->logger = $logger;
			}

			protected function doSend(SentMessage $message): void
			{
				$this->sent++;
				$this->logger->error('transport failed while sending');
			}

			public function __toString(): string
			{
				return 'logging://';
			}
		};
		$this->setConfig('enableMailQueue', false);
		$this->swap('mailer.transport', $transport);
		$this->app()->container()->decache('mailer');

		$logger->pushHandler(
			new XenForoMailHandler($this->app()->mailer(), 'logs@example.com', 'Subject', Logger::ERROR)
		);
		$logger->error('it broke');

		$this->assertSame(1, $transport->sent);
	}
}
