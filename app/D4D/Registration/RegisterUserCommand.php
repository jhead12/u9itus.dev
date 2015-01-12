<?php namespace Larabook\Registration;

class RegisterUserCommand{


public $username;

public $email;

public $password;

function _contruct($username, $email, $password)
{
	$this->username = $username;
	$this->email 	= $email;
	$this->password = $password;

}

}