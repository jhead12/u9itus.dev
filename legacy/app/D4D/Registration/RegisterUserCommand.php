<?php namespace D4D\Registration;


class RegisterUserCommand {

					public $firstName;
                 	public $lastName;
                 	public $telephone;
                 	public $email;
                 	public $username;
                 	public $password;
                 	public $sex;
                 	public $ip_address;
                 	public $terms;
                 	public $affid;
                 	public $country;
                 	public $refid;

    function _construct(
					$firstName,
                 	$lastName,
                 	$telephone,
                 	$email,
                 	$username,
                 	$password,
                 	$sex,
                 	$ip_address,
                 	$terms,
                 	$affid,
                 	$country,
                 	$refid)
	{


		$this->firstName 	= $firstName;
		$this->telephone 	= $telephone;
		$this->lastName 	= $lastName;
		$this->email 		= $email;
		$this->country 		= $country;
		$this->sex 			= $sex;
		$this->username 	= $username;
		$this->password 	= $password;
		$this->ip_address 	= $ip_address;
		$this->affid		= $affid;
		$this->terms 		= $terms;
		$this->refid 		= $refid;


	
	}
}