<?php namespace Hampel\Monolog;

use Hampel\Monolog\SubContainer\MonologApi;
use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
	public function install(array $stepParams = [])
	{
		// Nothing to do
	}

	public function upgrade(array $stepParams = [])
	{
		// Nothing to do
	}

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if (\XF::$versionId >= 2030000) { // XF 2.3+
            $this->enqueuePostUpgradeCleanUp();
        }
    }

	public function uninstall(array $stepParams = [])
	{
		// Nothing to do
	}

	public function checkRequirements(&$errors = [], &$warnings = [])
	{
		$vendorDirectory = sprintf("%s/vendor", $this->addOn->getAddOnDirectory());
		if (!file_exists($vendorDirectory))
		{
			$errors[] = "vendor folder does not exist - cannot proceed with addon install";
		}

		// a warning, not an error, so a forum on a newer XenForo can still install or upgrade
		if (!MonologApi::isTestedOnThisXenForo())
		{
			$warnings[] = "This version of Monolog Logging Service has not been tested on XenForo 2.4 or "
				. "later. It is expected to work, but check for a newer version that has been.";
		}
	}
}