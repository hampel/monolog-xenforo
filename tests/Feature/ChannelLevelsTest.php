<?php namespace Tests\Feature;

use Monolog\Handler\StreamHandler;
use Tests\TestCase;

/**
 * `channels` in a config.php section: a level for one channel, so a Debug trial turns up one
 * add-on rather than every add-on on the forum.
 */
class ChannelLevelsTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOptions([
			'monologAddVisitorExtra' => false,
			'monologAddRequestId' => false,
			'monologFileMinimumLogLevel' => 300, // WARNING
		]);
	}

	/**
	 * On Monolog 2 the logger asks isHandling() with a level and no channel, so this also proves
	 * the handler does not drop a record before it can see whose it is.
	 */
	public function test_one_channel_can_be_raised_to_debug()
	{
		$this->setConfig('monolog', ['file' => ['channels' => ['myaddon' => 'debug']]]);

		$this->app()['monolog']->channel('myaddon')->debug('traced');
		$this->app()['monolog']->channel('another')->debug('still quiet');

		$lines = $this->logLines();
		$this->assertCount(1, $lines);
		$this->assertStringContainsString('myaddon.DEBUG: traced', $lines[0]);
	}

	public function test_one_channel_can_be_lowered()
	{
		$this->setConfig('monolog', ['file' => ['channels' => ['noisy' => 'error']]]);

		$this->app()['monolog']->channel('noisy')->warning('not wanted');
		$this->app()['monolog']->channel('another')->warning('wanted');

		$lines = $this->logLines();
		$this->assertCount(1, $lines);
		$this->assertStringContainsString('another.WARNING: wanted', $lines[0]);
	}

	public function test_the_section_level_still_applies_to_every_other_channel()
	{
		$this->setConfig('monolog', ['file' => ['level' => 'error', 'channels' => ['myaddon' => 'info']]]);

		$this->app()['monolog']->channel('another')->warning('below error');
		$this->app()['monolog']->channel('another')->error('at error');
		$this->app()['monolog']->channel('myaddon')->info('at info');

		$lines = $this->logLines();
		$this->assertCount(2, $lines);
		$this->assertStringContainsString('another.ERROR', $lines[0]);
		$this->assertStringContainsString('myaddon.INFO', $lines[1]);
	}

	public function test_an_entry_that_is_not_a_level_leaves_that_channel_at_the_section_level()
	{
		$this->setConfig('monolog', ['file' => ['channels' => ['myaddon' => 'loud', 'other' => 'debug']]]);

		$this->app()['monolog']->channel('myaddon')->debug('ignored');
		$this->app()['monolog']->channel('myaddon')->warning('kept');

		$lines = $this->logLines();
		$this->assertCount(1, $lines);
		$this->assertStringContainsString('myaddon.WARNING: kept', $lines[0]);
	}

	public function test_without_channels_the_file_handler_is_not_wrapped()
	{
		$this->assertInstanceOf(StreamHandler::class, $this->app()['monolog']->container('handler.file'));
	}

	public function test_email_takes_its_own_channels()
	{
		$this->fakesMail();
		$this->setOptions([
			'monologSendEmail' => ['enabled' => true, 'email' => 'logs@example.com'],
			'monologEmailMinimumLogLevel' => 400, // ERROR
		]);
		$this->setConfig('monolog', ['email' => ['channels' => ['myaddon' => 'warning']]]);

		$monolog = $this->app()['monolog'];
		$monolog->channel('another')->warning('not emailed');
		$monolog->channel('myaddon')->warning('emailed');
		$monolog->channel('myaddon')->close();

		$this->assertMailSentTimes(1);
		$this->assertMailSent(function ($mail)
		{
			$html = (string) $mail->getHtmlBody();

			return strpos($html, 'emailed') !== false && strpos($html, 'not emailed') === false;
		});
	}
}
