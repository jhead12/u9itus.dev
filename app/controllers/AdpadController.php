<?php

class AdpadController extends \BaseController {

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

    public function getList(){

        header("content-type: text/xml");

        return View::make('account.callback.list');
    }
      public function getForgotPin(){

        $pin = mt_rand(0, 0x3fff | 0x800);

        $user           = User::find(Auth::user()->id);
            $user->pin = $pin;

                //Cache::put('pin', $user);


        if($user->save()){
            Mail::send(
                'emails.auth.pin', array(
                    'link'=> $pin,
                    'firstName'=> Auth::user()->firstName,
                    'username' => Auth::user()->username),
                function($message)use ($user){
                    $message->to($user->email, $user->username)->subject('Here is your pin');
                });



            }
            return Redirect::route('dialpad')->with('global-success',' A new pin has been sent to your email address.');



    }
     public function getAd($id){


        //This Opens the Modal with the Marketer information


        try{
            DB::connection('mongodb')->collection('marketers')->get();



        }catch (Exception $e){

            return $e->getMessage();
        }

        $str_id = intval($id);

        $marketer = Marketer::find($id );

        //return $marketer;


        return View::make('account.modals.addials')->with('marketer',$marketer);


    }
       public function postAddialCheck(){
        //Addial-confirm
        $data = Input::all();
        $marketers = Marketer::all();
        if ( $data['pin'] === Auth::user()->pin)
        {
            $id = $data['currentId'];
            //$id = $data['merchid'];
            $marketer = Marketer::find($id);
            $amount = $marketer->amount;
            //If marketer exist Create an Addials and update the amount of "Addials" in the marketer database
            if($marketer){
                $ads = $marketer->amount;
                $serial = $userID = str_random(15);
                $id = $marketer->_id;
                //If the marketer doesn't have any more addials
                if($amount ===0){
                    Marketer::destroy($id);
                    return Redirect::route('home')->with('global-warning','This AdDial campaign is completed.You will not earn credit for this campaign.');
                }
                else {
                    $addial = Addial::create(array(
                        'id' => $serial,
                        'user' => $marketer->company_name,
                        'campainId'     => $marketer->_id,
                        'currentAmount' => $marketer->amount,
                        'beforeAmount' => $ads,
                        'userclickId' => Auth::user()->userid,
                        'completed'     => false
                    ));
                }
                //return View::make('account.addials.business')->with('marketer',$marketer);
                $decrement = $marketer->decrement('amount');
                if($addial){
                    $saleTracker = new Pap_Api_SaleTracker('http://dial4dough.com/affiliates/scripts/sale.php');
                    $saleTracker->setAccountId('default1');
                    $saleTracker->setVisitorId(Auth::user()->userid);
                    $sale2 = $saleTracker->createSale();
                    $sale2->setAffiliateID(Auth::user()->userid);
                    $sale2->setCampaignID(Auth::user()->campaignid);
                    $sale2->setOrderID($serial);
                    $sale2->setTotalCost('1.75');
                    $saleTracker->register();
                    //return  $marketer;
                    return View::make('account.addials.business')->with('marketer',$marketer);
                };
            };
        }else{
            return 'error';
        }
        //Marketer::find('id','=',$str_id)->decrement('addials',$amount);
        //Update Timestamp
        //Marketer::save();
    }
        public function getPhoneStatus(){

        $callSid = Input::get('sid');




        // Your Account Sid and Auth Token from twilio.com/user/account
        $sid = "AC72c41dbce1ed193f2c496413e65ec33b";
        $token = "5710d01603dbd4fdddc6bd764a936073";
        $client = new Services_Twilio($sid, $token);

        // Get an object from its sid. If you do not have a sid,
        // check out the list resource examples on this page
        $call = $client->account->calls->get($callSid);
        echo $call->status;


    }
      public function postRejectCall(){


//Block All incoming calls to app.
        header('Content-Type: text/xml');
        // Set your voice URL to http://yourapp.com/reject.php


        $blacklist = array('+16105557069');

        if (in_array($_REQUEST['From'], $blacklist)):
            echo "<Response><Reject/></Response>";
        else:
            echo "<Response><Reject /></Response>";
        endif;

    }
    // Dial Pad Functionality.
    /*Need to set up ajax to color cordinate the link on the page.*/
    public function getDialpad(){
        //return 'This is the Bronze page';
         //$addials = Addial::all();




        $userid = Auth::user()->userid;
        //If there is an user Addial that has a completed status of true. Then take the addial off the dialpad.Other wise show it.
          $addials = Addial::where('userclickId','=',$userid)->where('completed','=',false)->remember(60)->get();
            //return $addials;
            
         //$notCompleted = Addial::where('userclickId','=',$userid)->where('completed','=',false)->distinct()->get(array('campaignId'));
         $marketers = Marketer::where('amount','>', 0)->simplePaginate(5);


        //Check if Uncompleted Addial Still has a related campaign. If not Destroy Addial.
         
        if($addials || $marketers ){

            //return 'yes';

             $_marketer = Marketer::distinct()->get(array('orderId'));
             $_addials = Addial::distinct()->get(array('orderId'));


            for ($i =0; $i < count($_addials); ++$i){

                $_id = $_addials[$i];
                $marketers = Marketer::where('orderId','!=',$_id[$i])->remember(60)->get();


            }
            //$addials_id = $addials;

            //return $marketers;
                
            //$marketers = Marketer::paginate(5);
            //for ($i = 0; $i < count($_addials); ++$i) {
                //$_id = $_addials[$i];

               // $marketers = Marketer::where('_id','=',$_id[$i])->get();

                return View::make('account.dialpad')->with('marketers',$marketers);

            }
            else{

                return 'no';

            $marketers = Marketer::remember(60);

            return View::make('account.dialpad')->with('marketers',$marketers);


        }
        return View::make('home')->with('global-danger','There was a problem, please try again. If problem persist please contact Dial4dough');
    }






}
