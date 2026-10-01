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

		// a warning rather than an error, so a forum upgraded to 2.4 can still rebuild or upgrade
		// this add-on - channels log nothing there until a 2.4-aware release
		if (!MonologApi::supportsThisXenForo())
		{
			$warnings[] = "This version of Monolog Logging Service does not support XenForo 2.4 or later. "
				. "Add-ons using it will keep working, but nothing will be logged until you install a "
				. "version that supports XenForo 2.4.";
		}
	}
}