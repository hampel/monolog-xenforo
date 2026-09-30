<?php namespace Tests\Feature;

use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * The contract consuming add-ons rely on in 4.x, pinned before the 5.0 rewrite: every consumer
 * checks for the `monolog` container key and calls newChannel() on it. 5.0 keeps newChannel() as
 * a deprecated alias, so these must pass unchanged on both sides of the rewrite.
 */
class NewChannelTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->useTemporaryInternalData();
		$this->setOption('monologAddVisitorExtra', false);
	}

	public function test_the_monolog_container_key_exists_when_the_addon_is_loaded()
	{
		$this->assertTrue($this->app()->container()->offsetExists('monolog'));
	}

	public function test_new_channel_returns_a_psr3_logger()
	{
		$this->assertInstanceOf(LoggerInterface::class, $this->app()['monolog']->newChannel('myaddon'));
	}

	public function test_a_record_at_the_file_level_is_written_under_the_channel_name()
	{
		$this->app()['monolog']->newChannel('myaddon')->warning('something happened', ['a' => 'b']);

		$lines = $this->logLines();

		$this->assertCount(1, $lines);
		$this->assertMatchesRegularExpression(
			'/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] myaddon\.WARNING: something happened \{"a":"b"\} \[\]$/',
			$lines[0]
		);
	}

	public function test_a_record_below_the_file_level_is_not_written()
	{
		$this->setOption('monologFileMinimumLogLevel', 300); // WARNING

		$this->app()['monolog']->newChannel('myaddon')->info('too quiet');

		$this->assertSame([], $this->logLines());
	}

	public function test_the_file_level_option_is_respected()
	{
		$this->setOption('monologFileMinimumLogLevel', 100); // DEBUG

		$this->app()['monolog']->newChannel('myaddon')->debug('verbose');

		$this->assertCount(1, $this->logLines());
	}

	public function test_nothing_is_written_when_the_log_file_is_disabled()
	{
		$this->setOption('monologLogFile', ['enabled' => false, 'logfile' => 'monolog.log']);

		$this->app()['monolog']->newChannel('myaddon')->error('nowhere to go');

		$this->assertSame([], $this->logLines());
	}

	public function test_the_log_file_name_is_relative_to_internal_data()
	{
		$this->setOption('monologLogFile', ['enabled' => true, 'logfile' => 'custom.log']);

		$this->app()['monolog']->newChannel('myaddon')->error('elsewhere');

		$this->assertSame([], $this->logLines());
		$this->assertCount(1, $this->logLines('custom.log'));
	}

	public function test_each_channel_writes_under_its_own_name()
	{
		$this->app()['monolog']->newChannel('first')->warning('one');
		$this->app()['monolog']->newChannel('second')->warning('two');

		$lines = $this->logLines();

		$this->assertCount(2, $lines);
		$this->assertStringContainsString('] first.WARNING: one', $lines[0]);
		$this->assertStringContainsString('] second.WARNING: two', $lines[1]);
	}

	public function test_the_visitor_is_added_to_extra_when_enabled()
	{
		$this->setOption('monologAddVisitorExtra', true);
		$this->actingAsMember(['user_id' => 123, 'username' => 'Visitor']);

		$this->app()['monolog']->newChannel('myaddon')->warning('who did this');

		$this->assertStringEndsWith('{"visitor":{"userid":123,"username":"Visitor"}}', $this->logLines()[0]);
	}

	public function test_the_visitor_is_not_added_when_disabled()
	{
		$this->actingAsMember(['user_id' => 123, 'username' => 'Visitor']);

		$this->app()['monolog']->newChannel('myaddon')->warning('who did this');

		$this->assertStringNotContainsString('visitor', $this->logLines()[0]);
	}
}
