<?php namespace D4D\Handlers;

class AccountEventHandler {


	public function onStart($userNew)
	{

		
		//put the start time into the started controller and reference it on the dialpad
	}

	public function subscribe($events)
	{
	
		$events->listen('on.start','D4D\Handlers\AccountEventHandler@onStart');
		

	}

}