<?php namespace Tests\Feature;

use Hampel\Monolog\Cli\Command\Config;
use Hampel\Monolog\Cli\Command\Validate;
use Monolog\Handler\TestHandler;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

/**
 * monolog:config - what this forum is told - and monolog:validate - whether it can do it.
 */
class CommandsTest extends TestCase
{
	/** @var string */
	private $internalData;

	protected function setUp(): void
	{
		parent::setUp();

		$this->internalData = $this->useTemporaryInternalData();
		$this->fakesMail();
	}

	/**
	 * XenForo loads every add-on's command classes to list them, so one that cannot load takes down
	 * every command in cmd.php, not only its own.
	 */
	public function test_both_commands_are_valid_xenforo_commands()
	{
		$runner = new \XF\Cli\Runner();
		$isValid = new \ReflectionMethod($runner, 'isValidCommandClass');
		$isValid->setAccessible(true);

		$this->assertTrue($isValid->invoke($runner, Config::class));
		$this->assertTrue($isValid->invoke($runner, Validate::class));
	}

	public function test_config_shows_each_setting_and_where_it_comes_from()
	{
		$this->setConfig('monolog', ['file' => ['level' => 'error'], 'site' => 'mysite']);

		[$code, $output] = $this->runCommand(new Config());

		$this->assertSame(0, $code);
		$this->assertMatchesRegularExpression('/level \.+ Error \(config\.php\)/', $output);
		$this->assertMatchesRegularExpression('/format \.+ line \(options\)/', $output);
		$this->assertMatchesRegularExpression('/site \.+ mysite \(config\.php\)/', $output);
		$this->assertStringContainsString($this->internalData . '/monolog.log', $output, 'paths are printed whole');
		$this->assertMatchesRegularExpression('/formatter \.+ not set/', $output);
	}

	public function test_config_writes_and_sends_nothing()
	{
		$this->runCommand(new Config());

		$this->assertSame([], $this->logLines());
		$this->assertNoMailSent();
	}

	public function test_validate_unattended_skips_the_sweep_and_sends_nothing()
	{
		$this->setOption('monologSendEmail', ['enabled' => true, 'email' => 'logs@example.com']);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertMatchesRegularExpression('/\[    \] level sweep +skipped - --unattended/', $output);
		$this->assertSame([], $this->logLines());
		$this->assertNoMailSent();
	}

	/**
	 * The count is the check: at Warning, five of the eight levels belong in the file - warning,
	 * error, critical, alert and emergency.
	 */
	public function test_the_sweep_proves_the_file_level()
	{
		$this->setOption('monologFileMinimumLogLevel', 300); // Warning

		[$code, $output] = $this->runCommand(new Validate());

		$this->assertSame(0, $code);
		$this->assertStringContainsString('5 of 8 written, as expected at Warning', $output);
		$this->assertCount(5, $this->logLines());
	}

	public static function formats(): array
	{
		return ['line' => ['line'], 'json' => ['json']];
	}

	/**
	 * What a mail transport logging its transmissions does: during the sweep, another record in the
	 * same file quotes one of the sweep's - the emailed copy - so it mentions the run id too. Only
	 * the sweep's own records are counted, and the other line is reported rather than counted.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('formats')]
	public function test_another_record_quoting_the_sweep_is_not_counted($format)
	{
		$this->setOption('monologFileMinimumLogLevel', 100);
		$this->setConfig('monolog', [
			'file' => ['format' => $format],
			'handlers' => [function ()
			{
				return new class extends \Monolog\Handler\AbstractHandler
				{
					public function handle($record): bool
					{
						if ($record['level'] === 400)
						{
							\XF::app()->get('monolog')->channel('mailer')->debug('Transmission', ['content' => $record['message']]);
						}

						return false;
					}
				};
			}],
		]);

		[$code, $output] = $this->runCommand(new Validate());

		$this->assertSame(0, $code, $output);
		$this->assertStringContainsString('8 of 8 written, as expected at Debug', $output);
		$this->assertStringContainsString('1 other line mentions this run', $output);
		$this->assertCount(9, $this->logLines(), 'the quoting record really is in the file');
	}

	public function test_a_wrong_count_shows_the_lines_that_do_not_fit()
	{
		$this->setOption('monologFileMinimumLogLevel', 100);
		$this->setConfig('monolog', ['handlers' => [function ()
		{
			// a second handler on the same file: every record is written twice
			return new \Monolog\Handler\StreamHandler(\Hampel\Monolog\Option\LogFile::path(), 400);
		}]]);

		[$code, $output] = $this->runCommand(new Validate());

		$this->assertSame(1, $code);
		$this->assertStringContainsString('12 of 8 written, but 8 were expected at Debug', $output);
		$this->assertMatchesRegularExpression('/monolog-validate\.ERROR: monolog:validate [0-9a-f]+: error/', $output,
			'a line that does not fit is shown');
	}

	public function test_a_custom_formatter_falls_back_to_counting_mentions()
	{
		$this->setOption('monologFileMinimumLogLevel', 100);
		$this->setConfig('monolog', ['file' => ['formatter' => function ()
		{
			return new \Monolog\Formatter\LineFormatter("%message%\n");
		}]]);

		[$code, $output] = $this->runCommand(new Validate());

		$this->assertSame(0, $code, $output);
		$this->assertStringContainsString('8 of 8 written, as expected at Debug', $output);
		$this->assertStringContainsString('custom formatter', $output);
	}

	public function test_the_sweep_sends_one_email_when_email_is_on()
	{
		$this->setOption('monologSendEmail', ['enabled' => true, 'email' => 'logs@example.com']);

		[$code, $output] = $this->runCommand(new Validate());

		$this->assertSame(0, $code);
		$this->assertStringContainsString('4 record(s) at Error or above sent as one email to logs@example.com', $output);
		$this->assertMailSentTimes(1);
	}

	public function test_an_unwritable_log_file_fails_validation()
	{
		$readOnly = $this->internalData . '/read-only';
		mkdir($readOnly, 0555);
		$this->setConfig('monolog', ['file' => ['path' => $readOnly . '/sub/forum.log']]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);
		chmod($readOnly, 0755);

		$this->assertSame(1, $code);
		$this->assertStringContainsString('[fail] log file', $output);
		$this->assertStringContainsString('Validation failed', $output);
	}

	public function test_an_unusable_config_value_warns_and_still_passes()
	{
		$this->setConfig('monolog', ['file' => ['level' => 'loud']]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertStringContainsString("[warn] file.level", $output);
		$this->assertStringContainsString('Validated, with warnings', $output);
	}

	public function test_a_factory_that_builds_the_wrong_thing_fails()
	{
		$this->setConfig('monolog', ['handlers' => [
			function () { return new TestHandler(); },
			function () { return new \stdClass(); },
		]]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(1, $code);
		$this->assertMatchesRegularExpression('/\[ ok \] handlers #1 +Monolog\\\\Handler\\\\TestHandler/', $output);
		$this->assertMatchesRegularExpression('/\[fail\] handlers #2 +builds stdClass/', $output);
	}

	/**
	 * A probe that throws is a failed check, not the end of the run.
	 */
	public function test_a_check_that_throws_does_not_end_the_run()
	{
		$this->fakesErrors();
		$this->setConfig('monolog', ['handlers' => [function () { throw new \RuntimeException('cannot connect'); }]]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(1, $code);
		$this->assertStringContainsString('[fail] stack', $output);
		$this->assertStringContainsString('cannot connect', $output);
		$this->assertStringContainsString('Level sweep', $output, 'the run carried on past it');
	}

	private function runCommand($command, array $input = []): array
	{
		$tester = new CommandTester($command);
		$code = $tester->execute($input, ['decorated' => false]);

		return [$code, $tester->getDisplay()];
	}
}
