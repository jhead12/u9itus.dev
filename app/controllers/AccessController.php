<?php

class AccessController extends \BaseController {

	public  $user;

	 function _construct(User $user)
	{
		$this->user = $user;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		 return View::make('pages.login');
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		//
	}

	/**
	 * post password recovery
	 *
	 * @return Get Recovery email
	 */
	public function getRecover($code){
		$user = User::where('code', '=', $code)
			->where('password_temp','!=', '');



		if(count($user)){
			$user = $user -> first();


			$user ->password_temp   = '';
			$user->code             = '';



			if($user->save()){

				return View::make('account.newpassword')->with('user',$user);
			}else{
				return Redirect::route('home')
					->with('global-danger','You were not able to recover your account. Please try again.');

			};

		};
	}

	/**
	 * Forgot Password access.
	 *
	 * @return view of Password
	 */
	public function forgotIndex()
	{
		return View::make('pages.access.forgot');
	}

	public function postForgot()
	{

                $validator = Validator::make(Input::all(),User::$emailOnly
                    );

                if($validator -> fails()) {
                    return Redirect::back()
                            ->withErrors($validator)
                            ->withInput();

                } else {


                    // change password

                    $user = User::where('email', '=',Input::get('email'))->first();
					dd($user);

                    //return count($user);

                    if(count($user)){

                        $user               = $user->first();
                        $code               = str_random(60);
                        $password           = str_random(10);
                        $user->code             = $code;
                        $user->password_temp    = Hash::make($password);


                    if($user-> save()){

                        Mail::send('emails.auth.forgot', array('url'=>URL::route('account-recover',$code), 'username'=>$user->username, 'password'=>$password),function($message)use($user){
                            $message->to($user->email, $user->username)->subject('Your new password');

                        });
                        return Redirect::route('home')
                                    ->with('global-success','Please check your email to continue.');
                    }

                    }
                    return Redirect::back()
                                ->with('global-danger', 'Could not request new password. You may have to sign up for a new account. ');
                }
             

	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}


	/**
	 * Post the New Password Changes
	 *
	 * @return Response
	 */
	public function postNewPassword(){



		$validator  = Validator::make(Input::all(),
			array(

				'password'      =>  'required|min:6',
				'password_again'=>  'required|same:password'
			)
		);


		if($validator ->fails()) {
			//redirect
			return Redirect::route('new-password')
				->withErrors($validator);
		}
		else{

			$user = User::where('userid','=',Input::get('userid'))->first();
			$password       = Input::get('password');




			$user ->password = Hash::make($password);








			if($user->save()){
				return Redirect::route('login')
					->with('global-success','Your password has been updated. You can log in with your new password. ');
			}

			else{
				return View::make('pages.access.newpassword')
					->with('global-danger', 'There are no accounts with that email address. Please sign up.')->withInput();

			}

		}
	}






	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @return Response
	 */
	public function destroy()
	{
		  Auth::logout();
        return Redirect::route('home');
        //return 'sign out';
	}


}
