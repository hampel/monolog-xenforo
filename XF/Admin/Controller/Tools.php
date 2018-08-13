<?php namespace Hampel\Monolog\XF\Admin\Controller;

class Tools extends XFCP_Tools
{
	public function actionTestMonolog()
	{
		$this->setSectionContext('testMonolog');

		$messages = [];
		$results = false;
		$test = '';

		if ($this->isPost())
		{
			$test = $this->filter('test', 'str');

			/** @var AbstractTest $tester */
			$tester = $this->app->container()->create('monolog.test', $test, [$this]);
			if ($tester)
			{
				$results = $tester->run();
				$messages = $tester->getMessages();
			}
			else
			{
				return $this->error(\XF::phrase('monolog_this_test_could_not_be_run'), 500);
			}
		}

		$viewParams = compact('results', 'messages', 'test');
		return $this->view('XF:Tools\TestMonolog', 'monolog_tools_test_monolog', $viewParams);
	}
}