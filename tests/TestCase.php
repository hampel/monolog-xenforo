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
