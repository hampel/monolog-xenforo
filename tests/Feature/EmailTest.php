<?php namespace Tests\Feature;

use Tests\TestCase;

/**
 * The email handler, pinned before the 5.0 rewrite replaces it with one built on XenForo's own
 * Mail class.
 *
 * These run against XenForo 2.3, where fakesMail() swaps in a Symfony Mailer transport; the XF 2.2
 * branch, which sends through SwiftMailer, cannot be exercised by this framework.
 *
 * Records are buffered for the request and sent when the handlers close, so each test closes the
 * channel where a request would end.
 */
class EmailTest extends TestCase
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
			'monologEmailSubject' => 'Monolog [{board}]',
			'boardTitle' => 'Test Board',
		]);
	}

	public function test_a_record_at_the_email_level_is_sent_when_the_channel_closes()
	{
		$channel = $this->app()['monolog']->newChannel('myaddon');
		$channel->error('it broke');

		$this->assertNoMailSent();

		$channel->close();

		$this->assertMailSentTimes(1);
		// the body is Monolog's HtmlFormatter table - one row per field - not a log line
		$this->assertMailSent(function ($mail)
		{
			$html = (string) $mail->getHtmlBody();

			return $mail->getTo()[0]->getAddress() === 'logs@example.com'
				&& $mail->getSubject() === 'Monolog [Test Board]'
				&& strpos($html, '>ERROR</h1>') !== false
				&& strpos($html, 'it broke') !== false
				&& strpos($html, 'myaddon') !== false;
		});
	}

	public function test_a_record_below_the_email_level_is_not_sent()
	{
		$channel = $this->app()['monolog']->newChannel('myaddon');
		$channel->warning('only a warning');
		$channel->close();

		$this->assertNoMailSent();
	}

	public function test_nothing_is_sent_when_email_is_disabled()
	{
		$this->setOption('monologSendEmail', ['enabled' => false, 'email' => 'logs@example.com']);

		$channel = $this->app()['monolog']->newChannel('myaddon');
		$channel->error('it broke');
		$channel->close();

		$this->assertNoMailSent();
	}

	public function test_the_recipient_defaults_to_the_board_contact_address()
	{
		$this->setOptions([
			'monologSendEmail' => ['enabled' => true, 'email' => ''],
			'contactEmailAddress' => 'contact@example.com',
		]);

		$channel = $this->app()['monolog']->newChannel('myaddon');
		$channel->error('it broke');
		$channel->close();

		$this->assertMailSent(function ($mail)
		{
			return $mail->getTo()[0]->getAddress() === 'contact@example.com';
		});
	}

	public function test_records_in_one_request_arrive_in_one_email()
	{
		$channel = $this->app()['monolog']->newChannel('myaddon');
		$channel->error('first');
		$channel->critical('second');
		$channel->close();

		$this->assertMailSentTimes(1);
	}

	public function test_a_repeated_record_within_the_timeout_is_not_sent_again()
	{
		$channel = $this->app()['monolog']->newChannel('myaddon');

		$channel->error('same again');
		$channel->close();

		$channel->error('same again');
		$channel->close();

		$this->assertMailSentTimes(1);
	}
}
