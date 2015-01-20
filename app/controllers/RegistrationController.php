<?php

use D4D\Commanding\CommandBus;
use D4D\Registration\RegisterUserCommand;

class RegistrationController extends \BaseController  {

	protected $commandBus;


	function _construct(CommandBus $commandBus)
	{

		$this->commandBus = $commandBus;
	}
	
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		return View::make('pages.registration.create');
	}
	public function store()
	{

		
			
		// Create an Interface where the user is logged into pap, using geten('user name')
		//validation
		// if not valid, go back
		// check if the user exist in PAP
		// if the user is in pap but not in Front DB, get the info, and return it to the fron database
		// then, create a user and send a email with a previous user
		// send user an email with the login info

		// else if the user is new, Create a user in the pap database,
		// then create a user in the front database
		// send email to the user.
		    //This should go into its own file
                    $validator = Validator::make(Input::all(), User::$rules);
                                    if($validator->fails() ){

                    return Redirect::back()->withErrors($validator)->withInput();

                 }
                 else{

                 

                    extract(Input::all());
                  
					$affid          = Session::get('id');
					$country        = Input::get('country2');


				
                 	$command = new RegisterUserCommand(
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
						$refid
                 	);

                 	
                 		dd($command);
                 	$this->commandBus->execute($command);
					//return Route::to('thankyou');


                 }

		
		

	}


	

}
