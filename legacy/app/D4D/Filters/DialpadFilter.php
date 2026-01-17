<?php namespace D4D\Filters;

use Illuminate\Routing\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Response;


use Addial;
use Marketer;
use Str;
use Cache;
use Carbon\Carbon;

class DialpadFilter  {



	public function newUser()
	{
		//If this user has subscriber within 24 hours, then Session Store - Show the message of addials not available.
		//Define the current mark of time.
		//If now is not out of the 24 hour period then put a session value Key that the if statement in the view will recoginze
		 $date = Carbon::now();
		 $user = Auth::check();
		 

		 // dd($user);


	}

   
   public function AdFilter(){

   	

	$addials = Addial::where('completed','=', true)->get();
	$marketers = Marketer::remember(60);
	
	//return $addials;
	if($addials){

			
			//$id = Addial::distinct('_id')->get();
			//return  $addials;
			foreach ($addials as $key => $value) {
				# code...
				$id = $value->campaignId;


				$marketer = Marketer::where('orderId','!=',$id)->get();

			if($marketer){

				return View::make('account.dialpad')->with('marketers', $marketer);
			}

			}


			
			
			

	}
	

 

    }


}