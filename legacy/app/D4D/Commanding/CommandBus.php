<?php namespace D4D\Commanding;

use Illuminate\Foundation\Application;

class CommandBus {

	/*
	* @var \Illuminate\Foundation\Application
	*/
	private $app;

	protected $commandTranslator;



function _construct(Application $app, CommandTranslator $commandTranslator)
{
	$this->app = $app;
	$this->commandTranslator = $commandTranslator;
	
}


	public function execute($command)
	{
	
		$handler = $this->commandTranslator->toCommandHandler($command);
		

		return $this->app->make($handler)->handle($command);

	}
}