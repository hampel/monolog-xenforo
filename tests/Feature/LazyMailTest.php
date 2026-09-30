<?php namespace Tests\Feature;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * The email handler touches XenForo's mailer only when it sends.
 *
 * Building the mailer fires mailer_transport_setup, and an add-on answering that event may ask
 * for a log channel. In 4.x, creating a channel built the mail handler, which built the mailer -
 * so that add-on recursed until the stack ran out.
 */
class LazyMailTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->fakesMail();
		$this->setOptions([
			'monologAddVisitorExtra' => false,
			'monologSendEmail' => ['enabled' => true, 'email' => 'logs@example.com'],
			'monologEmailMinimumLogLevel' => 400, // ERROR
		]);
		$this->app()->container()->decache('mailer');
	}

	public function test_creating_a_channel_does_not_build_the_mailer()
	{
		$this->app()['monolog']->channel('myaddon');

		$this->assertFalse($this->app()->container()->isCached('mailer'));
	}

	public function test_a_record_below_the_email_level_does_not_build_the_mailer()
	{
		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->warning('only a warning');
		$channel->close();

		$this->assertFalse($this->app()->container()->isCached('mailer'));
	}

	/**
	 * In the default stack the deduplication buffer absorbs a record logged during the send, so
	 * this holds even without XenForoMailHandler's own guard - XenForoMailHandlerTest covers that,
	 * unbuffered.
	 */
	public function test_a_record_logged_while_the_email_is_sending_does_not_send_again()
	{
		$channel = $this->app()['monolog']->channel('myaddon');

		// a transport that logs its own activity at ERROR, as a mail add-on reporting a failure would
		$transport = new class($channel) extends AbstractTransport
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
		$this->swap('mailer.transport', $transport);
		$this->app()->container()->decache('mailer');

		$channel->error('it broke');
		$channel->close();
		$channel->close();

		$this->assertSame(1, $transport->sent);
		$this->assertStringContainsString('transport failed while sending', implode("\n", $this->logLines()));
	}
}
