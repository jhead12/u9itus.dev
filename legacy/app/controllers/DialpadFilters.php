<?php

class DialpadFilter {

   
   public function filter(){

   	

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


			
			
			

	}else{
		

	}
	

 

    }

}