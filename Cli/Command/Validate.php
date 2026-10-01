<?php namespace Hampel\Monolog\Cli\Command;

use Hampel\Monolog\Cli\RendersReport;
use Hampel\Monolog\Config as MonologConfig;
use Hampel\Monolog\Option\EmailMinimumLogLevel;
use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\LogFile;
use Hampel\Monolog\Option\LogFormat;
use Hampel\Monolog\Option\SendEmail;
use Hampel\Monolog\SubContainer\MonologApi;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\HandlerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Whether this forum can actually log what it is configured to: the log file can be written, the
 * stack builds, and a record at every level lands where the levels say it should.
 *
 * The level sweep writes one record at each of the eight levels to the `monolog-validate` channel.
 * That is a send - it reaches the log file, the email recipient and any handler config.php adds -
 * and it is the only proof that a level threshold and a destination both work, so it runs by
 * default. --unattended skips it, for a deploy or cron gate nobody is watching; the checks that
 * are facts about the configuration still run.
 *
 * Exit code: 1 if any check failed, 0 otherwise - a warning does not fail, so a gate can rely on it.
 */
class Validate extends Command
{
	use RendersReport;

	private const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

	protected function configure()
	{
		$this
			->setName('monolog:validate')
			->setDescription('Check that logging works: the log file, the handlers, and a record at every level')
			->addOption('unattended', null, InputOption::VALUE_NONE,
				'Skip the level sweep, which writes and emails records - for a gate nobody is watching');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;
		$unattended = (bool) $input->getOption('unattended');

		$this->heading('Environment');
		$this->environment();

		// facts about the configuration: these report under --unattended too, so they stay above it
		$this->heading('config.php');
		$this->configValues();

		$this->heading('Email');
		$this->emailLevel();

		$this->heading('Log file');
		$path = $this->probe('log file', function ()
		{
			return $this->logFile();
		});

		$this->heading('Stack');
		$this->probe('stack', function ()
		{
			$this->stack();
		});

		$this->heading('Level sweep');
		if ($unattended)
		{
			$this->checkSkip('level sweep', 'skipped - --unattended');
		}
		else
		{
			$this->probe('level sweep', function () use ($path)
			{
				$this->sweep($path);
			});
		}

		$this->report->writeln('');
		if ($this->failed)
		{
			$this->report->writeln('<error>Validation failed - logging is not working as configured</error>');
			return 1;
		}

		$this->report->writeln($this->warned ? '<comment>Validated, with warnings</comment>' : '<info>Validated</info>');
		return 0;
	}

	private function environment(): void
	{
		if (MonologApi::isTestedOnThisXenForo())
		{
			$this->checkOk('XenForo', \XF::$version);
		}
		else
		{
			$this->checkWarn('XenForo', \XF::$version . ' - this version of the add-on has not been tested on it');
		}

		$this->checkOk('Monolog', Config::monologVersion());
	}

	/**
	 * A config.php value that cannot be used is ignored and its option decides - which is safe, and
	 * also a setting someone wrote that is doing nothing.
	 */
	private function configValues(): void
	{
		if (!MonologConfig::all())
		{
			$this->checkSkip("\$config['monolog']", 'not set - the options page decides everything');
			return;
		}

		$problems = 0;
		foreach (['file', 'email'] AS $section)
		{
			$value = MonologConfig::get($section, 'level');
			if ($value !== null && MonologConfig::toLevel($value) === null)
			{
				$problems++;
				$this->checkWarn("{$section}.level", var_export($value, true) . ' is not a level - the option decides');
			}
		}

		$format = MonologConfig::get('file', 'format');
		if ($format !== null && !in_array($format, LogFormat::FORMATS, true))
		{
			$problems++;
			$this->checkWarn('file.format', var_export($format, true) . " is not 'line' or 'json' - the option decides");
		}

		$dedup = MonologConfig::get('email', 'dedup');
		if ($dedup !== null && !(is_int($dedup) && $dedup >= 0))
		{
			$problems++;
			$this->checkWarn('email.dedup', var_export($dedup, true) . ' is not a whole number of seconds - the option decides');
		}

		if (!$problems)
		{
			$this->checkOk("\$config['monolog']", 'every value is usable');
		}
	}

	/**
	 * Email is for alerts. Below Warning it carries the routine records add-ons log - jobs run,
	 * mail sent - so every request that logs anything sends one, and a mail transport that logs its
	 * own sends adds one more. A warning, not a failure: it works, and a deliberate test at Debug
	 * should still pass.
	 */
	private function emailLevel(): void
	{
		if (!SendEmail::isEnabled())
		{
			$this->checkSkip('email level', 'email is off');
			return;
		}

		$level = EmailMinimumLogLevel::get();
		$name = FileMinimumLogLevel::LEVELS[$level] ?? (string) $level;

		$level < 300
			? $this->checkWarn('email level', "{$name} - every record at {$name} or above is emailed; Error is the usual level for email")
			: $this->checkOk('email level', "{$name}, to " . SendEmail::getAddress());
	}

	/**
	 * Whether the log file can be written - or, if it does not exist yet, created. Checked as the
	 * user running this command, which is usually not the web server's.
	 */
	private function logFile(): ?string
	{
		$path = LogFile::path();
		if ($path === null)
		{
			$this->checkSkip('log file', 'file logging is off');
			return null;
		}

		if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path))
		{
			$this->checkSkip('log file', "{$path} is a stream, not a file - nothing to check");
			return null;
		}

		$user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : '';
		$as = $user !== '' ? " (as {$user} - the web server may run as another user)" : '';

		if (file_exists($path))
		{
			if (is_dir($path))
			{
				$this->checkFail('log file', "{$path} is a directory");
				return null;
			}

			is_writable($path)
				? $this->checkOk('log file', "{$path} is writable{$as}")
				: $this->checkFail('log file', "{$path} is not writable{$as}");

			return $path;
		}

		// the stream handler creates the file, and any missing directories, on its first write
		$dir = dirname($path);
		while (!file_exists($dir) && dirname($dir) !== $dir)
		{
			$dir = dirname($dir);
		}

		is_dir($dir) && is_writable($dir)
			? $this->checkOk('log file', "{$path} does not exist yet; {$dir} is writable{$as}")
			: $this->checkFail('log file', "{$path} cannot be created - {$dir} is not writable{$as}");

		return $path;
	}

	/**
	 * Builds the logger every channel shares, then each config.php factory on its own, so a bad
	 * entry is named rather than only logged.
	 */
	private function stack(): void
	{
		$logger = \XF::app()->get('monolog')->channel('monolog-validate');
		$handlers = method_exists($logger, 'getHandlers') ? $logger->getHandlers() : [];
		$this->checkOk('logger', count($handlers) . ' handler(s) on every channel');

		$factories = [
			'handlers' => [(array) (MonologConfig::get('handlers') ?? []), HandlerInterface::class],
			'processors' => [(array) (MonologConfig::get('processors') ?? []), null],
		];
		$formatter = MonologConfig::get('file', 'formatter');
		if ($formatter !== null)
		{
			$factories['file.formatter'] = [[$formatter], FormatterInterface::class];
		}

		$any = false;
		foreach ($factories AS $key => [$entries, $type])
		{
			foreach ($entries AS $i => $factory)
			{
				$any = true;
				$label = count($entries) > 1 ? "{$key} #" . ($i + 1) : $key;
				$this->factory($label, $factory, $type);
			}
		}

		if (!$any)
		{
			$this->checkSkip('config.php factories', 'none - no handlers, processors or formatter set there');
		}
	}

	private function factory(string $label, $factory, ?string $type): void
	{
		if (!is_callable($factory))
		{
			$this->checkFail($label, 'not callable - it is skipped');
			return;
		}

		$built = call_user_func($factory);
		$valid = $type === null ? is_callable($built) : $built instanceof $type;
		$name = is_object($built) ? get_class($built) : gettype($built);

		$valid
			? $this->checkOk($label, $name)
			: $this->checkFail($label, "builds {$name}, which is not usable - it is skipped");
	}

	/**
	 * One record at each level, tagged with this run's id so the email deduplication cannot
	 * suppress it as a repeat of an earlier run. Proves the levels by counting what reached the
	 * log file; reports what was emailed, since only its arrival proves delivery.
	 */
	private function sweep(?string $path): void
	{
		$run = bin2hex(random_bytes(4));
		$db = \XF::db();
		$errorsBefore = (int) $db->fetchOne('SELECT MAX(error_id) FROM xf_error_log');
		$offset = $path !== null && is_file($path) ? (int) filesize($path) : 0;

		$logger = \XF::app()->get('monolog')->channel('monolog-validate');
		foreach (self::LEVELS AS $level)
		{
			$logger->log($level, "monolog:validate {$run}: {$level}", ['event' => 'monolog.validate', 'run' => $run]);
		}
		$logger->close(); // the email is sent here, as at the end of a request

		$this->checkOk('records', count(self::LEVELS) . " written to monolog-validate, run {$run}");

		if ($path === null)
		{
			$this->checkSkip('log file', 'no log file to count');
		}
		else
		{
			$fileLevel = FileMinimumLogLevel::get();
			$expected = $this->atOrAbove($fileLevel);
			[$own, $others] = is_file($path) ? $this->sweepLines($path, $offset, $run) : [[], []];
			$written = count($own);
			$at = FileMinimumLogLevel::LEVELS[$fileLevel] ?? $fileLevel;

			$notes = [];
			if (MonologConfig::get('file', 'formatter') !== null)
			{
				$notes[] = 'custom formatter - every line mentioning this run was counted';
			}
			if ($others)
			{
				$notes[] = count($others) . ' other line' . (count($others) === 1 ? ' mentions' : 's mention')
					. ' this run - another record quoting it, such as a logged copy of the email - not counted';
			}
			$note = $notes ? ' (' . implode('; ', $notes) . ')' : '';

			if ($written === $expected)
			{
				$this->checkOk('log file', "{$written} of 8 written, as expected at {$at}{$note}");
			}
			else
			{
				$this->checkFail('log file', "{$written} of 8 written, but {$expected} were expected at {$at}{$note}");
				foreach (array_slice(array_merge($own, $others), 0, 12) AS $line)
				{
					$this->report->writeln('         ' . mb_substr(rtrim($line), 0, 160));
				}
			}
		}

		if (SendEmail::isEnabled())
		{
			$emailLevel = EmailMinimumLogLevel::get();
			$count = $this->atOrAbove($emailLevel);
			$this->checkOk('email', "{$count} record(s) at " . (FileMinimumLogLevel::LEVELS[$emailLevel] ?? $emailLevel)
				. ' or above sent as one email to ' . SendEmail::getAddress() . ' - only its arrival proves delivery');
		}
		else
		{
			$this->checkSkip('email', 'email is off');
		}

		$errors = $db->fetchAll('SELECT message FROM xf_error_log WHERE error_id > ? ORDER BY error_id', $errorsBefore);
		$errors
			? $this->checkFail('server error log', count($errors) . ' new: ' . strtok($errors[0]['message'], "\n"))
			: $this->checkOk('server error log', 'nothing logged during the sweep');
	}

	private function atOrAbove(int $level): int
	{
		return count(array_filter(array_keys(FileMinimumLogLevel::LEVELS), function ($l) use ($level)
		{
			return $l >= $level;
		}));
	}

	/**
	 * The lines from where the file ended before the sweep that carry this run's id, split into the
	 * sweep's own records and anything else mentioning it. A record elsewhere can quote the sweep -
	 * a mail transport logging the email it sent, say - and must not be counted as one of its eight.
	 *
	 * @return array{0: string[], 1: string[]} [own records, other lines mentioning the run]
	 */
	private function sweepLines(string $path, int $offset, string $run): array
	{
		clearstatcache(true, $path);
		$prefix = "monolog:validate {$run}: ";
		$custom = MonologConfig::get('file', 'formatter') !== null;

		$own = [];
		$others = [];
		$handle = fopen($path, 'r');
		fseek($handle, $offset);
		while (($line = fgets($handle)) !== false)
		{
			if (strpos($line, "monolog:validate {$run}") === false)
			{
				continue;
			}

			if ($custom || $this->isSweepRecord($line, $prefix))
			{
				$own[] = $line;
			}
			else
			{
				$others[] = $line;
			}
		}
		fclose($handle);

		return [$own, $others];
	}

	/**
	 * One of the sweep's own records, in either built-in format: on the monolog-validate channel,
	 * with a message that starts with the sweep's. A custom formatter's layout is unknown, so the
	 * caller counts mentions instead and says so.
	 */
	private function isSweepRecord(string $line, string $prefix): bool
	{
		$json = json_decode(trim($line), true);
		if (is_array($json))
		{
			return ($json['channel'] ?? '') === 'monolog-validate'
				&& strpos((string) ($json['message'] ?? ''), $prefix) === 0;
		}

		return (bool) preg_match('/^\[[^\]]*\] monolog-validate\.[A-Z]+: ' . preg_quote($prefix, '/') . '/', $line);
	}

	/**
	 * No check may end the run: a validation that stops at its first problem hides the rest, on
	 * the one occasion the whole list was wanted.
	 */
	private function probe(string $label, callable $probe)
	{
		try
		{
			return $probe();
		}
		catch (\Throwable $e)
		{
			\XF::logException($e, false, "monolog:validate - {$label}: ");
			$this->checkFail($label, get_class($e) . ': ' . strtok($e->getMessage(), "\n"));

			return null;
		}
	}
}
