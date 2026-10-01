<?php namespace Hampel\Monolog\Cli;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Settings rows and check rows, in the same layout as hampel/console-report's ReportsSettings and
 * RendersChecks - which this cannot use: that package needs PHP 8.3, and XenForo ships its own
 * symfony/console. The four check outcomes mean what they mean everywhere else: `[ ok ]` was proved,
 * `[warn]` works now and bites later, `[fail]` cannot do what it is configured to, and a blank marker
 * did not apply. A skip is never a pass.
 */
trait RendersReport
{
	/** @var OutputInterface */
	protected $report;

	protected int $labelWidth = 24;

	protected bool $failed = false;

	protected bool $warned = false;

	/** @var string|null the colour muted() uses, once known */
	private static ?string $mutedColour = null;

	protected function heading(string $title): void
	{
		$this->report->writeln('');
		$this->report->writeln("  <fg=green;options=bold>{$title}</>");
	}

	/**
	 * A setting and its value, with a dotted leader. An empty value reads as `not set`, never blank:
	 * a blank line reads as a setting that is fine.
	 */
	protected function detail(string $label, string $value): void
	{
		$value = $value === '' ? $this->muted('not set') : $value;
		$dots = max(2, 33 - mb_strlen($label));

		$this->report->writeln("  {$label} " . $this->muted(str_repeat('.', $dots)) . " {$value}");
	}

	/**
	 * Text in grey, where the console has grey. XenForo 2.2 ships its own fork of symfony/console,
	 * which knows only the eight basic colours and throws on `gray` - taking the command down at its
	 * first annotation - so there it is the terminal's default colour instead. Never write a grey
	 * foreground tag directly; CommandsTest fails if anything does.
	 */
	protected function muted(string $text): string
	{
		if (self::$mutedColour === null)
		{
			try
			{
				new OutputFormatterStyle('gray');
				self::$mutedColour = 'gray';
			}
			catch (\Throwable $e)
			{
				self::$mutedColour = 'default';
			}
		}

		return '<fg=' . self::$mutedColour . ">{$text}</>";
	}

	protected function checkOk(string $label, string $detail = ''): void
	{
		$this->check('<info>[ ok ]</info>', $label, $detail);
	}

	protected function checkWarn(string $label, string $detail = ''): void
	{
		$this->warned = true;
		$this->check('<comment>[warn]</comment>', $label, $detail);
	}

	protected function checkFail(string $label, string $detail = ''): void
	{
		$this->failed = true;
		$this->check('<error>[fail]</error>', $label, $detail);
	}

	protected function checkSkip(string $label, string $detail = ''): void
	{
		$this->check('[    ]', $label, $detail);
	}

	private function check(string $marker, string $label, string $detail): void
	{
		$padding = str_repeat(' ', max(0, $this->labelWidth - mb_strlen($label)));

		$this->report->writeln(rtrim("  {$marker} {$label}{$padding} {$detail}"));
	}
}
