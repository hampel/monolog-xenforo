<?php namespace Tests;

use Hampel\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	/**
	 * @var string $rootDir path to the XenForo root directory, relative to the addon path
	 *
	 * No trailing slash!
	 */
	protected $rootDir = '../../../..';

	/**
	 * @var array $addonsToLoad load only this addon, so that another addon's vendor tree and
	 * listeners cannot affect the run
	 */
	protected $addonsToLoad = ['Hampel/Monolog'];

	/**
	 * @var string|null the internal_data directory for this test, when one was asked for
	 */
	private $internalDataPath;

	/**
	 * With MONOLOG3=1, resolve Monolog and psr/log from tests/monolog3 instead of this add-on's
	 * vendor tree and XenForo's - which is what a XenForo that provides Monolog 3 itself would do,
	 * since its class loader is consulted before any add-on's. So the suite runs against the
	 * classes this add-on would actually get there.
	 */
	public function createApplication()
	{
		$app = parent::createApplication();

		if (self::simulatingMonolog3())
		{
			$vendor = __DIR__ . '/monolog3/vendor';
			if (!is_dir($vendor))
			{
				throw new \LogicException('MONOLOG3=1 needs: composer install -d tests/monolog3');
			}

			// setPsr4 replaces every path for the prefix, including the ones XenForo just added
			// for this add-on's own vendor tree
			\XF::$autoLoader->setPsr4('Monolog\\', [$vendor . '/monolog/monolog/src/Monolog']);
			\XF::$autoLoader->setPsr4('Psr\\Log\\', [$vendor . '/psr/log/src']);
		}

		return $app;
	}

	/**
	 * Every test starts from this add-on's defaults - no `$config['monolog']` and every option at
	 * its default value - whatever the install's own config.php and options page hold. Otherwise a
	 * developer trying a setting changes what the suite tests. A test that needs a value sets it
	 * with setConfig() or setOption(), and the framework restores options after each test.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->setConfig('monolog', []);

		$defaults = [];
		$options = $this->app()->finder('XF:Option')->where('addon_id', 'Hampel/Monolog')->fetch();
		foreach ($options AS $option)
		{
			$defaults[$option->option_id] = $option->getDefaultValue();
		}
		$this->setOptions($defaults);
	}

	public static function simulatingMonolog3(): bool
	{
		return (bool) getenv('MONOLOG3');
	}

	/**
	 * Point internal_data at a directory unique to this test.
	 *
	 * The log file lives there, and so does XenForo's temp directory - which holds the email
	 * handler's deduplication store. A shared store would suppress an email in one test because
	 * another test, or an earlier run, had already sent the same message.
	 */
	protected function useTemporaryInternalData(): string
	{
		$this->internalDataPath = sys_get_temp_dir() . '/monolog-test-' . bin2hex(random_bytes(8));
		mkdir($this->internalDataPath);

		$this->setConfig('internalDataPath', $this->internalDataPath);

		return $this->internalDataPath;
	}

	/**
	 * The lines written to a log file under this test's internal_data, or none if it was never
	 * created - the stream handler only creates the file on its first write.
	 */
	protected function logLines(string $file = 'monolog.log'): array
	{
		$path = $this->internalDataPath . '/' . $file;
		if (!file_exists($path))
		{
			return [];
		}

		return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		if ($this->internalDataPath !== null && is_dir($this->internalDataPath))
		{
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($this->internalDataPath, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($files AS $file)
			{
				$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
			}
			rmdir($this->internalDataPath);
		}

		$this->internalDataPath = null;
	}
}
