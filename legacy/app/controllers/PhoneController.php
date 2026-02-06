<?php

class PhoneController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//
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
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
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
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}
	  /*
     * This is the function that will make the phone call to user.
     * */
    public function postMakecall(){





        //require 'twilio/sdk/Services/Twilio.php';
        //return 'A call will be made';
        /* Set our AccountSid and AuthToken */
        $AccountSid = "AC72c41dbce1ed193f2c496413e65ec33b";
        $AuthToken = "5710d01603dbd4fdddc6bd764a936073";

        /* Your Twilio Number or an Outgoing Caller ID you have previously validated
            with Twilio */


         $phone = Input::get('telephone');


//        $marketer = Marketer::all();
//        return json_encode($marketer);

        if($phone){
            $from= $phone; //Marketer Database Number of the user e.g Marketer::telephone()->phone1

        }else{
            return 'There was an error: You will not be able to receive credit from this marketer.';
        }
        /* Number you wish to call */
        /*The users number will be choosing e.g from the data base or Users selection*/
        $to= Input::get('called');


        /* Directory location for callback.php file (for use in REST URL)*/
        /*This is the url to the webpage where the user will see the info -- Ajax may be needed for live feedback*/
        $base = URL::to('/');

        $url = 'http://admin:1234@704a1a3e.ngrok.com/';

        /* Instantiate a new Twilio Rest Client */
        $client = new Services_Twilio($AccountSid, $AuthToken);



        if (!isset($_REQUEST['called']) || strlen($_REQUEST['called']) == 0) {
            $err = urlencode("Must specify your phone number");
            header("Location: index.php?msg=$err");
            die;
        }

        try{  /* make Twilio REST request to initiate outgoing call */
            $call = $client->account->calls->create($from, $to, $url . 'message',array(

            ));
        }catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }



        /* redirect back to the main page with CallSid */
        $msg = urlencode("Connecting to Addial ");
        return $call;
    }
    //    Addials

    public function postMessage(){

        //Get Selected marketer Telephone Number -
        //return Input::all();
        //Put the current marketer phone number in cache and retrieve it here to make marketer dynamic.
        //eg. Session::get('id',array())

        $marketer= Marketer::where('telephone','=','+5304255293')->get();

        //return $marketer[0]->audio_file;
        $config = array('username' => 'admin', 'password' => '1234');
        Httpauth::make($config)->secure();

        //Finding Current Marketer audio file.

        header('Content-Type: text/xml');




        echo "<Response>";
        echo "<Say>Hello Welcome to the Dial for dough add dials system. </Say>";
        echo "<Say>The advertiser is from the ". $marketer[0]->company_name ." </Say>";

        echo "<Play>".$marketer[0]->audio_file."</Play>";





        echo "<Gather action='http://localhost/action' method='GET'>";
        echo   "<Say>Please press 1 to retrieve your bonus and exit</Say>";
        echo  "<Say>Press 2 receive more information about this add dial and retrieve bonus.</Say>";
        echo "</Gather>";
        echo "</Response>";



    }


}
