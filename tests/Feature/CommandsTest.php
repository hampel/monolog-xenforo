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

	public static function emailLevels(): array
	{
		return [
			'Debug warns' => [100, '/\[warn\] email level +Debug - every record at Debug or above is emailed/'],
			'Notice warns' => [250, '/\[warn\] email level +Notice - every record at Notice or above is emailed/'],
			'Warning is fine' => [300, '/\[ ok \] email level +Warning, to logs@example\.com/'],
			'Error is fine' => [400, '/\[ ok \] email level +Error, to logs@example\.com/'],
		];
	}

	/**
	 * A static fact about the configuration, so it reports under --unattended: the run that sends
	 * nothing is the one that most needs to say the threshold is wrong.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('emailLevels')]
	public function test_an_email_level_below_warning_warns($level, $expected)
	{
		$this->setOptions([
			'monologSendEmail' => ['enabled' => true, 'email' => 'logs@example.com'],
			'monologEmailMinimumLogLevel' => $level,
		]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code, 'a warning does not fail validation');
		$this->assertMatchesRegularExpression($expected, $output);
	}

	public function test_email_off_skips_the_email_level_check()
	{
		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertMatchesRegularExpression('/\[    \] email level +email is off/', $output);
	}

	public function test_an_unusable_config_value_warns_and_still_passes()
	{
		$this->setConfig('monolog', ['file' => ['level' => 'loud']]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertStringContainsString("[warn] file.level", $output);
		$this->assertStringContainsString('Validated, with warnings', $output);
	}

	public function test_config_lists_each_sections_channel_levels()
	{
		$this->setConfig('monolog', ['file' => ['channels' => ['myaddon' => 'debug', 'noisy' => 'error']]]);

		[, $output] = $this->runCommand(new Config());

		$this->assertMatchesRegularExpression('/channels \.+ myaddon Debug, noisy Error \(config\.php\)/', $output);
	}

	public function test_a_channel_level_that_is_not_a_level_warns()
	{
		$this->setConfig('monolog', ['file' => ['channels' => ['myaddon' => 'loud']]]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertStringContainsString("[warn] file.channels", $output);
		$this->assertStringContainsString("'myaddon' => 'loud' is not a level", $output);
	}

	public function test_an_email_channel_below_warning_warns()
	{
		$this->setOptions([
			'monologSendEmail' => ['enabled' => true, 'email' => 'logs@example.com'],
			'monologEmailMinimumLogLevel' => 400,
		]);
		$this->setConfig('monolog', ['email' => ['channels' => ['myaddon' => 'debug']]]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertMatchesRegularExpression('/\[ ok \] email level +Error/', $output);
		$this->assertMatchesRegularExpression('/\[warn\] email level +myaddon at Debug/', $output);
	}

	/**
	 * The sweep writes to its own channel, so it counts against that channel's level.
	 */
	public function test_the_sweep_counts_against_its_own_channels_level()
	{
		$this->setOption('monologFileMinimumLogLevel', 300); // Warning
		$this->setConfig('monolog', ['file' => ['channels' => ['monolog-validate' => 'debug']]]);

		[$code, $output] = $this->runCommand(new Validate());

		$this->assertSame(0, $code);
		$this->assertStringContainsString('8 of 8 written, as expected at Debug', $output);
	}

	public function test_config_shows_the_slack_webhooks_host_and_never_the_webhook()
	{
		$this->setConfig('monolog', ['slack' => ['webhook' => 'https://hooks.slack.com/services/T/B/secret']]);

		[, $output] = $this->runCommand(new Config());

		$this->assertMatchesRegularExpression('/webhook \.+ set, to hooks\.slack\.com \(config\.php\)/', $output);
		$this->assertStringNotContainsString('secret', $output);
	}

	public function test_a_slack_level_below_warning_warns()
	{
		$this->setOptions([
			'monologSlack' => ['enabled' => true, 'webhook' => 'https://hooks.slack.com/services/T/B/secret'],
			'monologSlackMinimumLogLevel' => 200,
		]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertMatchesRegularExpression('/\[warn\] slack level +Info - every record at Info or above is posted/', $output);
		$this->assertStringNotContainsString('secret', $output);
	}

	public function test_a_slack_webhook_in_config_that_is_not_https_warns()
	{
		$this->setConfig('monolog', ['slack' => ['webhook' => 'http://hooks.slack.com/services/T/B/x']]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);

		$this->assertSame(0, $code);
		$this->assertStringContainsString('[warn] slack.webhook', $output);
		$this->assertMatchesRegularExpression('/\[    \] slack level +Slack is off/', $output);
	}

	public function test_the_sweep_posts_one_slack_message_when_slack_is_on()
	{
		$this->fakesHttp([new \GuzzleHttp\Psr7\Response(200, [], 'ok')]);
		$this->setOption('monologSlack', ['enabled' => true, 'webhook' => 'https://hooks.slack.com/services/T/B/x']);

		[$code, $output] = $this->runCommand(new Validate());

		$this->assertSame(0, $code);
		$this->assertStringContainsString('4 record(s) at Error or above posted as one message', $output);
		$this->assertHttpRequestSentTimes(1);
	}

	/**
	 * A failed post never reaches the code that logged; it goes to the server error log, which the
	 * sweep then reports as a failure. That last step reads xf_error_log, and XenForo writes it only
	 * when it finds the install lock - which a temporary internal_data lacks, and which it caches for
	 * the process - so here the fake error handler stands in, and the [fail] is proved on a real
	 * install instead.
	 */
	public function test_a_failed_slack_post_reaches_the_error_log_and_not_the_output()
	{
		$this->fakesErrors();
		$this->fakesHttp([new \GuzzleHttp\Psr7\Response(404, [], 'no_service')]);
		$this->setOption('monologSlack', ['enabled' => true, 'webhook' => 'https://hooks.slack.com/services/T/B/secret']);

		[, $output] = $this->runCommand(new Validate());

		$errors = $this->getErrorFake()->getExceptions();
		$this->assertCount(1, $errors);
		$this->assertStringStartsWith('Monolog: posting to Slack failed', $errors[0]['message']);
		$this->assertStringNotContainsString('secret', $errors[0]['message']);
		$this->assertStringNotContainsString('secret', $output);
	}

	/**
	 * XenForo 2.2's console fork throws on `gray`, which took monolog:config down there at its first
	 * annotation. Grey goes through RendersReport::muted(), which falls back where it is missing.
	 */
	public function test_no_command_writes_grey_except_through_muted()
	{
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/Cli')) AS $file)
		{
			if ($file->getExtension() !== 'php')
			{
				continue;
			}

			$source = file_get_contents($file->getPathname());
			$this->assertSame(0, preg_match('/<fg=gr[ae]y/', $source), $file->getFilename() . ' writes <fg=gray> directly');
		}
	}

	public static function strictCases(): array
	{
		return [
			'clean, no flag' => ['clean', false, 0],
			'clean, strict' => ['clean', true, 0],
			'warning, no flag' => ['warning', false, 0],
			'warning, strict' => ['warning', true, 2],
			'failure, no flag' => ['failure', false, 1],
			'failure, strict' => ['failure', true, 1],
			'failure and warning, strict' => ['both', true, 1],
		];
	}

	/**
	 * Without --strict the exit code is what it has been since the command shipped. With it, a
	 * warning is heard as 2 - and 1 still means failed, which is not the monitoring-plugin order.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('strictCases')]
	public function test_strict_makes_a_warning_exit_2_and_changes_nothing_else($state, $strict, $expected)
	{
		$this->fakesErrors();
		$config = [];
		if ($state === 'warning' || $state === 'both')
		{
			$config['file'] = ['level' => 'loud'];
		}
		if ($state === 'failure' || $state === 'both')
		{
			$config['handlers'] = [function () { return 'not a handler'; }];
		}
		$this->setConfig('monolog', $config);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true] + ($strict ? ['--strict' => true] : []));

		$this->assertSame($expected, $code, $output);
	}

	public function test_strict_does_not_skip_the_sweep()
	{
		[$code, $output] = $this->runCommand(new Validate(), ['--strict' => true]);

		$this->assertSame(0, $code);
		$this->assertStringContainsString('8 written to monolog-validate', $output);
	}

	/**
	 * Every output off is not broken, and not fine either: it used to report "[ ok ] 0 handler(s)".
	 */
	public function test_a_logger_with_no_handlers_is_a_warning()
	{
		$this->setConfig('monolog', ['file' => false, 'email' => false, 'slack' => false]);

		[$code, $output] = $this->runCommand(new Validate(), ['--unattended' => true]);
		[$strictCode] = $this->runCommand(new Validate(), ['--unattended' => true, '--strict' => true]);

		$this->assertMatchesRegularExpression('/\[warn\] logger +no handlers/', $output);
		$this->assertSame(0, $code);
		$this->assertSame(2, $strictCode);
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

		$logged = array_column($this->getErrorFake()->getExceptions(), 'message');
		$this->assertCount(1, $logged);
		$this->assertStringStartsWith('Monolog: monolog:validate - stack: ', $logged[0]);
	}

	private function runCommand($command, array $input = []): array
	{
		$tester = new CommandTester($command);
		$code = $tester->execute($input, ['decorated' => false]);

		return [$code, $tester->getDisplay()];
	}
}
