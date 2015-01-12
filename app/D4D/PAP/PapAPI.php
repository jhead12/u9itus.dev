<?php namespace D4D\PAP;


use Guzzle\Service\Client;

class PapAPI {

	protected $session;
	protected $affiliate;

	public function _contruct(Client $session){

	
					//PAP Session
			$affiliate = new Pap_Api_Affiliate($session);
            $this->$affiliate = $affiliate;


	}
	public function search($email){

		 //Check to see if he user in PAP
                   $this->$affiliate->setNotificationEmail($email);
                   
                 $response = $this->$affiliate->load();
                  

                    return $response->json();
                }




	}


}