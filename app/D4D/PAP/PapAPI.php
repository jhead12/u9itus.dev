<?php namespace D4D\PAP;

class PapApi implements PapInterface{

	public function signIn($command)
	{
			   $session = new Gpf_Api_Session("http://www.dialer.dial4dough.com/affiliates/scripts/server.php");
            if(!@$session->login("matrixblend@yahoo.com", "mc1282")) {
            Log::error("Cannot login. Message: ".$session->getMessage());
            }

            //PAP Session
                    $affiliate = new Pap_Api_Affiliate($session);

                    return $affiliate;
	}

	public function checkUser($commander)
	{

			    //Check to see if he user in PAP
                    $affiliate->setNotificationEmail($commander->email);
                    //$affiliate->setUserid('2005630');
                    $exist = true;

                                        try {
                $affiliate->load();
              } catch (Exception $e) {
                  //The User Does not exist
                 Log::error('postCreate '.$e->getMessage());
                 $exist = false;


              }//catch

	}

}

