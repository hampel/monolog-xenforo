<?php namespace Tests\Feature;

use Tests\TestCase;

/**
 * What counts as a repeat, for the email and Slack alerts.
 *
 * Messages are meant to be fixed strings, with everything that varies in the context - so a test
 * on level and message alone, which is Monolog's, calls two different errors the same one, and
 * suppresses the second. Each test here is two requests: log, close, log, close.
 */
class DeduplicationTest extends TestCase
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
	}

	public function test_the_same_record_again_is_a_repeat()
	{
		$this->request('myaddon', 'Server error logged', ['fingerprint' => 'TypeError:a.php:10']);
		$this->request('myaddon', 'Server error logged', ['fingerprint' => 'TypeError:a.php:10']);

		$this->assertMailSentTimes(1);
	}

	public function test_a_constant_message_with_no_fingerprint_is_still_a_repeat()
	{
		$this->request('myaddon', 'Payment callback rejected', ['status' => 400]);
		$this->request('myaddon', 'Payment callback rejected', ['status' => 500]);

		$this->assertMailSentTimes(1);
	}

	public function test_a_different_fingerprint_is_a_different_record()
	{
		$this->request('myaddon', 'Server error logged', ['fingerprint' => 'TypeError:a.php:10']);
		$this->request('myaddon', 'Server error logged', ['fingerprint' => 'ErrorException:b.php:99']);

		$this->assertMailSentTimes(2);
	}

	public function test_a_different_exception_is_a_different_record()
	{
		$this->request('myaddon', 'Job failed', ['exception' => new \RuntimeException('one')]);
		$this->request('myaddon', 'Job failed', ['exception' => new \LogicException('two')]);

		$this->assertMailSentTimes(2);
	}

	public function test_the_same_exception_thrown_again_is_a_repeat()
	{
		foreach ([1, 2] AS $attempt)
		{
			// one line, so both exceptions share a class, file and line
			$this->request('myaddon', 'Job failed', ['exception' => new \RuntimeException("attempt {$attempt}")]);
		}

		$this->assertMailSentTimes(1);
	}

	public function test_another_channel_with_the_same_message_is_a_different_record()
	{
		$this->request('first', 'Request failed');
		$this->request('second', 'Request failed');

		$this->assertMailSentTimes(2);
	}

	public function test_another_level_with_the_same_message_is_a_different_record()
	{
		$this->request('myaddon', 'Request failed', [], 'error');
		$this->request('myaddon', 'Request failed', [], 'critical');

		$this->assertMailSentTimes(2);
	}

	/**
	 * One new record sends the whole request, repeats included: they are its context.
	 */
	public function test_a_request_with_one_new_record_is_sent_whole()
	{
		$this->request('myaddon', 'already seen');

		$channel = $this->app()['monolog']->channel('myaddon');
		$channel->error('already seen');
		$channel->error('new this time');
		$channel->close();

		$this->assertMailSentTimes(2);
		$this->assertMailSent(function ($mail)
		{
			$html = (string) $mail->getHtmlBody();

			return strpos($html, 'new this time') !== false && strpos($html, 'already seen') !== false;
		});
	}

	public function test_a_record_older_than_the_timeout_is_sent_again()
	{
		$this->setConfig('monolog', ['email' => ['dedup' => 0]]);

		$this->request('myaddon', 'same again');
		$this->request('myaddon', 'same again');

		$this->assertMailSentTimes(2);
	}

	/**
	 * A forum upgrading from 5.0.0 has a store Monolog's handler wrote: `timestamp:LEVEL:message`.
	 * Those lines match nothing, do not break the read, and are gone after the first send.
	 */
	public function test_a_store_left_by_monologs_handler_is_ignored_and_replaced()
	{
		$store = \XF\Util\File::getTempDir() . '/monolog-dedup-email.log';
		file_put_contents($store, time() . ":ERROR:same again\n" . time() . ":ERROR:a message: with colons\nnot a line at all\n");

		$this->request('myaddon', 'same again');

		$this->assertMailSentTimes(1);
		$lines = file($store, FILE_IGNORE_NEW_LINES);
		$this->assertCount(1, $lines);
		$this->assertMatchesRegularExpression('/^\d+:[0-9a-f]{32}$/', $lines[0]);
	}

	private function request(string $channel, string $message, array $context = [], string $level = 'error'): void
	{
		$logger = $this->app()['monolog']->channel($channel);
		$logger->log($level, $message, $context);
		$logger->close();
	}
}
