<?php namespace Hampel\Monolog\Cli\Command;

use Hampel\Monolog\Cli\RendersReport;
use Hampel\Monolog\Config as MonologConfig;
use Hampel\Monolog\Option\AbstractConfigurableOption;
use Hampel\Monolog\Option\AddVisitorExtra;
use Hampel\Monolog\Option\AddWebExtra;
use Hampel\Monolog\Option\EmailDeduplicationTimeout;
use Hampel\Monolog\Option\EmailMinimumLogLevel;
use Hampel\Monolog\Option\EmailSubject;
use Hampel\Monolog\Option\FileMinimumLogLevel;
use Hampel\Monolog\Option\LogFile;
use Hampel\Monolog\Option\LogFormat;
use Hampel\Monolog\Option\SendEmail;
use Hampel\Monolog\Option\Site;
use Hampel\Monolog\SubContainer\MonologApi;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * What this forum is configured to log, and where each setting comes from - config.php or the
 * options page. It reads and prints; it writes nothing and sends nothing. monolog:validate is the
 * command that finds out whether any of it works.
 */
class Config extends Command
{
	use RendersReport;

	private const FROM_CONFIG = ' <fg=cyan>(config.php)</>';
	private const FROM_OPTIONS = ' <fg=gray>(options)</>';

	protected function configure()
	{
		$this
			->setName('monolog:config')
			->setDescription('Show the logging settings in use, and where each comes from');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;
		$app = \XF::app();

		$this->heading('Monolog');
		$addOn = $app->addOnManager()->getById('Hampel/Monolog');
		$this->detail('add-on', $addOn ? (string) $addOn->getJson()['version_string'] : '');
		$this->detail('XenForo', \XF::$version . (MonologApi::isTestedOnThisXenForo() ? '' : ' - not yet tested with this add-on'));
		$this->detail('Monolog', self::monologVersion());
		$this->detail('config.php', MonologConfig::all() ? "has a \$config['monolog'] block" : '');

		$this->heading('Log file');
		$this->detail('enabled', $this->yesNo(LogFile::isEnabled()) . $this->source(LogFile::class));
		$path = LogFile::path();
		$this->detail('path', $path === null ? '' : $path . (MonologConfig::string('file', 'path') !== null
			? self::FROM_CONFIG : ' <fg=gray>(options, inside internal_data)</>'));
		$this->detail('format', LogFormat::get() . $this->source(LogFormat::class));
		$this->detail('level', $this->level(FileMinimumLogLevel::get()) . $this->source(FileMinimumLogLevel::class));
		$this->detail('formatter', MonologConfig::get('file', 'formatter') !== null ? 'custom, from config.php' : '');

		$this->heading('Email');
		$this->detail('enabled', $this->yesNo(SendEmail::isEnabled()) . $this->source(SendEmail::class));
		if (SendEmail::isEnabled())
		{
			$to = SendEmail::getAddress();
			$isContact = MonologConfig::string('email', 'to') === null
				&& empty(\XF::options()->monologSendEmail['email']);
			$this->detail('to', $to . ($isContact ? ' <fg=gray>(the board contact address)</>'
				: (MonologConfig::string('email', 'to') !== null ? self::FROM_CONFIG : self::FROM_OPTIONS)));
		}
		else
		{
			$this->detail('to', '');
		}
		$this->detail('level', $this->level(EmailMinimumLogLevel::get()) . $this->source(EmailMinimumLogLevel::class));
		$this->detail('subject', EmailSubject::get() . $this->source(EmailSubject::class));
		$this->detail('deduplication', EmailDeduplicationTimeout::get() . ' seconds' . $this->source(EmailDeduplicationTimeout::class));

		$this->heading('Records');
		$this->detail('visitor', $this->yesNo(AddVisitorExtra::get()) . $this->source(AddVisitorExtra::class));
		$this->detail('web request', $this->yesNo(AddWebExtra::get()) . $this->source(AddWebExtra::class));
		$siteSource = Site::isLocked() ? self::FROM_CONFIG
			: ((string) (\XF::options()->monologSite ?? '') !== '' ? self::FROM_OPTIONS : " <fg=gray>(the board URL's host)</>");
		$this->detail('site', Site::get() . $siteSource . (LogFormat::get() === 'json' ? '' : ' - only JSON records carry it'));

		$this->heading('Extending');
		$this->detail('handlers in config.php', (string) count((array) (MonologConfig::get('handlers') ?? [])));
		$this->detail('processors in config.php', (string) count((array) (MonologConfig::get('processors') ?? [])));
		$listeners = 0;
		foreach ((array) $app->extension()->getListeners('hampel_monolog_setup') AS $hinted)
		{
			$listeners += count((array) $hinted);
		}
		$this->detail('hampel_monolog_setup listeners', (string) $listeners);

		$this->report->writeln('');

		return 0;
	}

	/**
	 * Which Monolog is loaded, and whose copy: this add-on's, or XenForo's own (2.4 bundles one).
	 */
	public static function monologVersion(): string
	{
		$file = (string) (new \ReflectionClass(Logger::class))->getFileName();
		$owner = strpos($file, '/addons/Hampel/Monolog/') !== false ? "this add-on's" : "XenForo's";

		return 'Monolog ' . Logger::API . ", {$owner} copy";
	}

	/**
	 * @param class-string<AbstractConfigurableOption> $optionClass
	 */
	private function source(string $optionClass): string
	{
		return $optionClass::isLocked() ? self::FROM_CONFIG : self::FROM_OPTIONS;
	}

	private function level(int $level): string
	{
		return FileMinimumLogLevel::LEVELS[$level] ?? (string) $level;
	}

	private function yesNo(bool $value): string
	{
		return $value ? 'yes' : 'no';
	}
}
