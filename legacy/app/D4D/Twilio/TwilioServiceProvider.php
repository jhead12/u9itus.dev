<?php namespace D4D\Twilio;


use Illuminate\Support\ServiceProvider;
use Config;


class TwilioServiceProvider extends ServiceProvider {


	public function subAccount(){

        //get the current account id
        //dynamically put the info within the 

		$account_sid = ''; 
		$auth_token = $_ENV(''); 
		$client = new Services_Twilio($account_sid, $auth_token); 

		$account = $client->accounts->create(array(  
		)); 

		$sid = $account->sid;


	}
	public function register(){

           return 'test';
    
        
         }




}