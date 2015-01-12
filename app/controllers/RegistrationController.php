<?php

class RegistrationController extends \BaseController {

	
	private $registrationForm;

	/**
	 * Show the form for creating a new resource.
	 *
	 * @param CommandBus $commandBus
	 */
	function _contruct(CommandBus $commandBus )
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
		return View::make('registration.create');
	}
	public function store()
	{
		new RegisterUserCommand;

		$this->commandBus->execute($command);

		   // $session = new Gpf_Api_Session("http://www.dialer.dial4dough.com/affiliates/scripts/server.php");
     //        if(!@$session->login("matrixblend@yahoo.com", "mc1282")) {
     //        Log::error("Cannot login. Message: ".$session->getMessage());
     //        }

     //                //This should go into its own file
     //                $validator = Validator::make(Input::all(), User::$rules);
     //                                if($validator->fails() ){

     //                return Redirect::back()->withErrors($validator)->withInput();

     //             }	
                 return Redirect::route('thankyou');
	}


	

}
