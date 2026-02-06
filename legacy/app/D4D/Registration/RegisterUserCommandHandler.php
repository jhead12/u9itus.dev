<?php namespace D4D\Registration;

use D4D\Commanding\CommandHandler;


class RegisterUserCommandHandler implements CommandHandler{

	public function handle($command)
	{
		var_dump('Delegate process of creating a new user');
	}

}

