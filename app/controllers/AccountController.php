<?php

class AccountController extends BaseController{

   

        protected $user;

    public function _construct(User $user, $session )
    {
        $this->user = $user;

 

    }

    public function index()
    {

        $users = $this->user->all();
    }


   
    /*
     * This is the Get Modal for the addial
     * */

   
    public function getPrivate(){
        return 'private Policy';

    }
    public function getTerms(){
        return View::make('agreements.terms');
    }
    public function getWelcome(){
        return View::make('account.welcome');
    }
    public function getBronzeSignup(){
        return View::make('account.bronze.payform');
    }
    public function getPlatinumSignup(){
        return View::make('account.platinum.form');
    }
    public function getGoldSignup(){
        return View::make('account.gold.form');
    }
    public function getHelp(){
        return 'Help';
    }
    

        

    public function getAdCorner(){
        return View::make('account.bronze.adcorner');
    }
    public function getCreate(){


            return View::make('account.create');
    }
  
    public function getConfirmation(){
        return View::make('marketing.confirmation');
    }
    public function getMarketingBase(){
        return View::make('marketing.market');
    }
    public function getActivate($code){
        $user = User::where('code', '=', $code)->where('active', '=', 0);
        if($user->count() ){
            $user= $user->first();
            //udate user to active state
            $user->active = 1;
            $user->code = '';
            $user->ftlogin = '1';
            $user->info = '1';
            //Membership Class (once payment is confirmed)
            if($user->save()){
                Auth::login($user);
                return Redirect::route('welcome')
                    ->with('global-success', 'Your Account is Now Activated. Choose your membership.');
            }
            return Redirect::route('home')
                ->with('global-danger', 'There was a error preventing a successful sign up. Please Try again later');

        }
        return Redirect::route('home')
            ->with('global-danger', 'There was a error preventing a successful sign up. Please Try again later');
    }
    public function getChangePassword(){
        return View::make('account.password');

    }
    public function getNewPassword(){
        return View::make('account.newpassword');
    }
    public function getForgotPassword(){
        return View::make('account.forgot');


    }
    public function getNotifications(){
        $session = new Gpf_Api_Session("https://www.dial4dough.com/affiliates/scripts/server.php");
        if(!$session->login(Auth::user()->email, Auth::user()->password, Gpf_Api_Session::AFFILIATE)) {
            die("Cannot login. Message: ".$session->getMessage());
        }
        header('Location: '.$session->getUrlWithSessionInfo('http://www.dial4dough.com/affiliates/scripts/panel.php'));

    }

//    PhoneSystem

    /*
    | POST Section
    */
    /*
     * This Function Turns off the Confirmation Info. Needs Instruction on how to use adDial systm
     * */
    public function getSelectInfoOff(){
        $user = Auth::user();



        $user->info = '0';
        if($user->save()){

            return Redirect::intended();
        }
    }

    public function postPlatinumPayment(){

        $session = new Gpf_Api_Session("https://www.dialer.dial4dough.com/affiliates/scripts/server.php");
        if(!@$session->login(getenv('PAP_USERNAME'), getenv('PAP_PASSWORD'))) {
            die("Cannot login. Message: ".$session->getMessage());
        }
        $affiliate = new Pap_Api_Affiliate($session);
        $payoutRequest = new Pap_Api_PayoutsGrid($session);

        //Stripe::setApiKey('sk_test_39vjod2CTub1sK7LzPChQPru');
        Stripe::setApiKey(Config::get('services.stripe.secret'));
        Stripe::setApiKey(Config::get('services.stripe.publishable_key'));
        //User::setStripeKey('sk_test_39vjod2CTub1sK7LzPChQPru');
        $user = Auth::user();
        //$user = User::find(1);
        //$payoutOptionId = '8444af30';



          //This should go into its own file
                    $validator = Validator::make(Input::all(), User::$payRulesExt);
                                    if($validator->fails() ){
                    return Redirect::back()->withErrors($validator)->withInput();

        }
        else{

            // 4242

            $paypal_email = Input::get('paypal_email');
            $street_address = Input::get('street_address');
            $city     = Input::get('city');
            $state = Input::get('state');
            $zip = Input::get('zip');
            $terms = Input::get('payagreement');
            $ftlogin = 0;

           if($terms=='on'){
               $terms = 1;
           }



                // Create a Stripe Token For Credit Card
                $token = Stripe_Token::create(array(
                    "card" => array(
                        "number" => Input::get('card'),
                        "exp_month" => Input::get('exp-month'),
                        "exp_year" => Input::get('exp-year'),
                        "cvc" => Input::get('cvc'),
                        "address_city"    => $city,
                        "address_line1"   => $street_address,
                        "address_state"   => $state,
                        "address_zip"     => $zip
                    )
                ));

                //If there was an error from the Try



            // Create a Platinum User.
            $platinum = '938b2b84';

            // Request to Pap Merchants for the Payouts API function
            $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'loadPayouts', $session);






            //$email = $user->email;
            $id = $user->userid;
            $affiliate->setUserid($id);
            //$affiliate->setNotificationEmail($email);


            // Try Loading the Affiliate Email
            try {
                $affiliate->load();
            } catch (Exception $e) {
                //The User Does not exist
                //return 'no entry';
                Log::error('postPlatinumPayment '.$e->getMessage());


            }



            // Gather the User Id from the Located field
            //$userId = $affiliate->getField('userid');
            $refid = $affiliate->getRefid();



            // Checks if the user is an Older account Holder.
            if($user->oldUser===1){
                //Check to see if he user in PAP
                $email = $user->email;
                //$affiliate->setUserid();
                $affiliate->setNotificationEmail($email);

                //return Auth::user()->userid;



                // Try Loading the Affiliate Email
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                    //The User Does not exist
                    //return 'no account';
                    Log::error('postPlatinumPayment '.$e->getMessage());
                }
                // Gather the User Id from the Located field
                $userId = $affiliate->getField('userid');


                // get the userid set the id with the current and define it.
                $request->setField('Id',$userId);




                // Send the request to change the the ID
                try {
                    $request->sendNow();

                } catch(Exception $e) {
                    Log::error('postPlatinumPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or try again.');
                }

                // Returns the Form with the changed ID
                $responseForm = $request->getForm();



                // Check if the Response form exist
                if ($responseForm->isSuccessful()) {
                    $minimumPayout = $responseForm->getFieldValue('minimumpayout');
                    $payoutOptionId = $responseForm->getFieldValue('payoutoptionid');

                }

                // Request another payout Form?
                $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'savePayouts', $session);


                // Request to Set the payouts with New options.
                $request->setField('Id',$userId);
                $request->setField('payoutoptionid', $payoutOptionId);
                $request->setField('code','');
                $request->setField('message','success');

                $request->setField('pp_email',$paypal_email);




                // Another reqest is neededs to
                // Git the Form userid from the pap Database
                $userId = $affiliate->getField('userid');
                $refid = $affiliate->getRefid();

                // Get the Pap UserId and add the data to the New User
                $affiliate->setUserid($userId);

                // Try is the affiliate database can be load the affiliate
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                     Log::error('postPlatinumPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');
                }


                // Set the Id as the $userid *Created id.
                $request->setField('Id',$userId);


                // Send the Changes of the  Field
                try {
                    $request->sendNow();
                } catch(Exception $e) {
                    //die('API call error: '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');
                }




                // Request the Form into an Object
                $responseForm = $request->getForm();

                // If more options Place below




                //return [$payoutOptionId];

                // On the affiliate Database Define these parameters
                $affiliate->setStatus('A');
                $affiliate->setData(3, $street_address);
                $affiliate->setData(4,$city);
                $affiliate->setData(5,$state);
                //$affiliate->setData(6,'United States');
                $affiliate->setData(10,$paypal_email);
                $affiliate->setData(7,$zip);
                $affiliate->assignToPrivateCampaign($platinum);



                // Try to add the changes to the affiliate database
                try {
                    if ($affiliate->save()) {
                        //echo "Affiliate saved successfuly";

                    }
                } catch (Exception $e) {
                     Log::error('postPlatinumPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or try again.');

                }

                // Make Changes to front Database Empty the old User Id and replace it with New Userid
                $user->userid = '';
                $user->userid = $userId;


                // This is the end of the First Request for the Old User
            }


            // If the User is new to the System
            else{
                //Set the refid -- check to see if in scope
                $refid = $affiliate->getRefid();
                //Check to see if he user in PAP
                $email = $user->email;
                $affiliate->setNotificationEmail($email);

                // Try Loading the Affiliate Email
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                    //The User Does not exist
                     Log::error('postPlatinumPayment '.$e->getMessage());
                }
                // Gather the User Id from the Located field
                $userId = $affiliate->getField('userid');



                // get the userid set the id with the current and define it.
                $request->setField('Id',$userId);




                // Send the request to change the the ID
                try {
                    $request->sendNow();

                } catch(Exception $e) {
                     Log::error('postPlatinumPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or try again.');
                }

                // Returns the Form with the changed ID
                $responseForm = $request->getForm();



                // Check if the Response form exist
                if ($responseForm->isSuccessful()) {
                    $minimumPayout = $responseForm->getFieldValue('minimumpayout');
                    $payoutOptionId = $responseForm->getFieldValue('payoutoptionid');

                }

                // Request another payout Form?
                $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'savePayouts', $session);


                // Request to Set the payouts with New options.
                $request->setField('Id',$userId);
                $request->setField('payoutoptionid', $payoutOptionId);
                $request->setField('code','');
                $request->setField('message','success');

                $request->setField('pp_email',$paypal_email);




                // Another reqest is neededs to
                // Git the Form userid from the pap Database
                $userId = $affiliate->getField('userid');

                // Get the Pap UserId and add the data to the New User
                $affiliate->setUserid($userId);

                // Try is the affiliate database can be load the affiliate
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                     Log::error('postPlatinumPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or try again.');
                }


                // Set the Id as the $userid *Created id.
                $request->setField('Id',$userId);


                // Send the Changes of the  Field
                try {
                    $request->sendNow();
                } catch(Exception $e) {
                    //die('API call error: '.$e->getMessage());
                     Log::error('postPlatinumPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or try again.');
                }




                // Request the Form into an Object
                $responseForm = $request->getForm();

                // If more options Place below




                //return [$payoutOptionId];

                // On the affiliate Database Define these parameters
                $affiliate->setStatus('A');
                $affiliate->setData(3, $street_address);
                $affiliate->setData(4,$city);
                $affiliate->setData(5,$state);
                //$affiliate->setData(6,'United States');
                $affiliate->setData(10,$paypal_email);
                $affiliate->setData(7,$zip);
                $affiliate->assignToPrivateCampaign($platinum);


                // Try to add the changes to the affiliate database
                try {
                    if ($affiliate->save()) {
                        //echo "Affiliate saved successfuly";

                    }
                } catch (Exception $e) {
                     Log::error('postPlatinumPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');

                }




                // This is the end of the First Request for the New User
            }



            };

        //credit Card Try
        try{
            $user->subscription('platinum')->create($token['id'],[
                'email'  => $user->email,
                'description'   => 'Platinum Membership:'." ' '".e($user->userid)
            ]);

        }catch(Stripe_CardError $e) {
             Log::error('postPlatinumPayment '.$e->getMessage());

            $body = $e->getJsonBody();
            $err  = $body['error'];

            return Redirect::route('platinum-signup')->with('global-danger',$err['message'])->withInput();

        }


            if($user){
                $user->paypal_email = '';
                $user->paypal_email = $paypal_email;
                $user->street_address  = $street_address;
                $user->city       = $city;
                $user->state      = $state;
                $user->zip       = $zip;
                $user->terms       =$terms;
                $user->ftlogin   = $ftlogin;
                $user->campaignid = $platinum;
                $user->oldUser= 0;
                $user->refid    = '';
                $user->refid    = $refid;



                //Before Going to the platinum Home there should be a email or thank you indication after purchase.
                $pin = Auth::user()->pin;
                $firstName = Auth::user()->firstName;
                $username = Auth::user()->username;



                if($user->save()){

                    Mail::send(
                        'emails.auth.pin', array(
                            'link'=>  $pin,
                            'firstName'=> $firstName,
                            'username' => $username),
                        function($message)use ($user){
                            $message->to($user->email, $user->username)->subject('Your Addial access pin');
                        });


                    return Redirect::route('dialpad')->with('global-confirmation','You have subscribed as Platinum Member. Click on the "Dialpad" button to start earning AdDials credits.');
                };
            };
        }
    public function postGoldPayment(){

        $session = new Gpf_Api_Session("https://www.dial4dough.com/affiliates/scripts/server.php");
        if(!@$session->login("matrixblend@yahoo.com", "mc1282")) {
            Log::error("Cannot login. Message: ".$session->getMessage());
        }
        $affiliate = new Pap_Api_Affiliate($session);
        $payoutRequest = new Pap_Api_PayoutsGrid($session);

        //Stripe::setApiKey('sk_test_39vjod2CTub1sK7LzPChQPru');
        Stripe::setApiKey(Config::get('services.stripe.secret'));
        Stripe::setApiKey(Config::get('services.stripe.publishable_key'));
        //User::setStripeKey('sk_test_39vjod2CTub1sK7LzPChQPru');
        $user = Auth::user();
        //$user = User::find(1);
        //$payoutOptionId = '8444af30';


        // Validator
        if(Request::ajax()){
            $errors = Input::all();

            if($errors['message']){


            }
        }

      
          //This should go into its own file
                    $validator = Validator::make(Input::all(), User::$payRulesExt);
                                    if($validator->fails() ){
                    return Redirect::back()->withErrors($validator)->withInput();

        }

        else{

            // 4242

            $paypal_email = Input::get('paypal_email');
            $street_address = Input::get('street_address');
            $city     = Input::get('city');
            $state = Input::get('state');
            $zip = Input::get('zip');
            $terms = Input::get('payagreement');
            $ftlogin = 0;

            if($terms=='on'){
                $terms = 1;
            }


            try{
                // Create a Stripe Token For Credit Card
                $token = Stripe_Token::create(array(
                    "card" => array(
                        "number" => Input::get('card'),
                        "exp_month" => Input::get('exp-month'),
                        "exp_year" => Input::get('exp-year'),
                        "cvc" => Input::get('cvc'),
                        "address_city"    => $city,
                        "address_line1"   => $street_address,
                        "address_state"   => $state,
                        "address_zip"     => $zip
                    )
                ));

                //If there was an error from the Try
            } catch(Stripe_CardError $e){
                $body = $e->getJsonBody();
                $err = $body['error'];
                 Log::error('postGoldPayment '.$e->getMessage());
                return Redirect::route('gold-signup')->with('global-danger',$err['message']);

            }


            // Create a Gold User.
            $gold = 'faa06c36';

            // Request to Pap Merchants for the Payouts API function
            $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'loadPayouts', $session);


            // Checks if the user is an Older account Holder.
            if($user->oldUser===1){
                //Check to see if he user in PAP
                $email = $user->email;
                //$affiliate->setUserid();
                $affiliate->setNotificationEmail($email);

                //return Auth::user()->userid;



                // Try Loading the Affiliate Email
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                    //The User Does not exist
                    //return 'no account';
                     Log::error('postGoldPayment '.$e->getMessage());
                }
                // Gather the User Id from the Located field
                $userId = $affiliate->getField('userid');


                // get the userid set the id with the current and define it.
                $request->setField('Id',$userId);




                // Send the request to change the the ID
                try {
                    $request->sendNow();

                } catch(Exception $e) {
                    Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }

                // Returns the Form with the changed ID
                $responseForm = $request->getForm();



                // Check if the Response form exist
                if ($responseForm->isSuccessful()) {
                    $minimumPayout = $responseForm->getFieldValue('minimumpayout');
                    $payoutOptionId = $responseForm->getFieldValue('payoutoptionid');

                }

                // Request another payout Form?
                $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'savePayouts', $session);


                // Request to Set the payouts with New options.
                $request->setField('Id',$userId);
                $request->setField('payoutoptionid', $payoutOptionId);
                $request->setField('code','');
                $request->setField('message','success');

                $request->setField('pp_email',$paypal_email);




                // Another reqest is neededs to
                // Git the Form userid from the pap Database
                $userId = $affiliate->getField('userid');

                // Get the Pap UserId and add the data to the New User
                $affiliate->setUserid($userId);

                // Try is the affiliate database can be load the affiliate
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                     Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }


                // Set the Id as the $userid *Created id.
                $request->setField('Id',$userId);


                // Send the Changes of the  Field
                try {
                    $request->sendNow();
                } catch(Exception $e) {
                    //die('API call error: '.$e->getMessage());
                     Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }




                // Request the Form into an Object
                $responseForm = $request->getForm();

                // If more options Place below




                //return [$payoutOptionId];

                // On the affiliate Database Define these parameters
                $affiliate->setStatus('A');
                $affiliate->setData(3, $street_address);
                $affiliate->setData(4,$city);
                $affiliate->setData(5,$state);
                //$affiliate->setData(6,'United States');
                $affiliate->setData(10,$paypal_email);
                $affiliate->setData(7,$zip);
                $affiliate->assignToPrivateCampaign($gold);


                // Try to add the changes to the affiliate database
                try {
                    if ($affiliate->save()) {
                        //echo "Affiliate saved successfuly";

                    }
                } catch (Exception $e) {
                     Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');

                }

                // Make Changes to front Database Empty the old User Id and replace it with New Userid
                $user->userid = '';
                $user->userid = $userId;


                // This is the end of the First Request for the Old User
            }


            // If the User is new to the System
            else{
                //Check to see if he user in PAP
                $email = $user->email;
                $affiliate->setNotificationEmail($email);



                // Try Loading the Affiliate Email
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                     Log::error('postGoldPayment '.$e->getMessage());
                    //The User Does not exist
                    //return 'no account';
                }
                // Gather the User Id from the Located field
                $userId = $affiliate->getField('userid');


                // get the userid set the id with the current and define it.
                $request->setField('Id',$userId);




                // Send the request to change the the ID
                try {
                    $request->sendNow();

                } catch(Exception $e) {
                     Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }

                // Returns the Form with the changed ID
                $responseForm = $request->getForm();



                // Check if the Response form exist
                if ($responseForm->isSuccessful()) {
                    $minimumPayout = $responseForm->getFieldValue('minimumpayout');
                    $payoutOptionId = $responseForm->getFieldValue('payoutoptionid');

                }

                // Request another payout Form?
                $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'savePayouts', $session);


                // Request to Set the payouts with New options.
                $request->setField('Id',$userId);
                $request->setField('payoutoptionid', $payoutOptionId);
                $request->setField('code','');
                $request->setField('message','success');

                $request->setField('pp_email',$paypal_email);




                // Another reqest is neededs to
                // Git the Form userid from the pap Database
                $userId = $affiliate->getField('userid');

                // Get the Pap UserId and add the data to the New User
                $affiliate->setUserid($userId);

                // Try is the affiliate database can be load the affiliate
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                    Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');
                }


                // Set the Id as the $userid *Created id.
                $request->setField('Id',$userId);


                // Send the Changes of the  Field
                try {
                    $request->sendNow();
                } catch(Exception $e) {
                    //die('API call error: '.$e->getMessage());
                    Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');
                }




                // Request the Form into an Object
                $responseForm = $request->getForm();

                // If more options Place below




                //return [$payoutOptionId];

                // On the affiliate Database Define these parameters
                $affiliate->setStatus('A');
                $affiliate->setData(3, $street_address);
                $affiliate->setData(4,$city);
                $affiliate->setData(5,$state);
                //$affiliate->setData(6,'United States');
                $affiliate->setData(10,$paypal_email);
                $affiliate->setData(7,$zip);
                $affiliate->assignToPrivateCampaign($gold);


                // Try to add the changes to the affiliate database
                try {
                    if ($affiliate->save()) {
                        //echo "Affiliate saved successfuly";

                    }
                } catch (Exception $e) {
                     Log::error('postGoldPayment '.$e->getMessage());
                    return View::make('gold-signup')->with('global-danger','There is a internal Error - You can contact us or Start over.');

                }




                // This is the end of the First Request for the New User
            }



        };

        //credit Card Try
        try{
            $user->subscription('gold')->create($token['id'],[
                'email'  => $user->email,
                'description'   => 'Gold Membership:'." ' '".e($user->userid)
            ]);

        }catch(Stripe_CardError $e) {
             Log::error('postGoldPayment '.$e->getMessage());

            $body = $e->getJsonBody();
            $err  = $body['error'];

            return Redirect::route('gold-signup')->with('global-danger',$err['message'])->withInput();

        }


        if($user){
            $user->paypal_email = '';
            $user->paypal_email = $paypal_email;
            $user->street_address  = $street_address;
            $user->city         = $city;
            $user->state        = $state;
            $user->zip          = $zip;
            $user->terms        = $terms;
            $user->campaignid   = $gold;
            $user->ftlogin      = $ftlogin;
            $user->oldUser      = 0;
            $user->info            =1;



            //Before Going to the gold Home there should be a email or thank you indication after purchase.
            $pin = Auth::user()->pin;
            $firstName = Auth::user()->firstName;
            $username = Auth::user()->username;



            if($user->save()){

                Mail::send(
                    'emails.auth.pin', array(
                        'link'=>  $pin,
                        'firstName'=> $firstName,
                        'username' => $username),
                    function($message)use ($user){
                        $message->to($user->email, $user->username)->subject('Your Dial4dough access pin');
                    });


                return Redirect::route('home')->with('global-confirmation','You are a Gold Member. Click on the "Dialpad" button to start earning AdDials credits.');
            };
        }}
    public function postBronzePayment(){

        $session = new Gpf_Api_Session("https://www.dial4dough.com/affiliates/scripts/server.php");
        if(!@$session->login("matrixblend@yahoo.com", "mc1282")) {
            die("Cannot login. Message: ".$session->getMessage());
        }
        $affiliate = new Pap_Api_Affiliate($session);
        $payoutRequest = new Pap_Api_PayoutsGrid($session);

        Stripe::setApiKey(Config::get('services.stripe.secret'));
        Stripe::setApiKey(Config::get('services.stripe.publishable_key'));
        //User::setStripeKey('sk_test_39vjod2CTub1sK7LzPChQPru');
        $user = Auth::user();
        //$user = User::find(1);
        //$payoutOptionId = '8444af30';


        // Validator
        if(Request::ajax()){
            $errors = Input::all();

            if($errors['message']){


            }
        }

       
          //This should go into its own file
                    $validator = Validator::make(Input::all(), User::$payRules);
                                    if($validator->fails() ){
                    return Redirect::back()->withErrors($validator)->withInput();

        }

        else{

            // 4242

            $paypal_email       = Input::get('paypal_email');
            $street_address     = Input::get('street_address');
            $city               = Input::get('city');
            $state              = Input::get('state');
            $zip                = Input::get('zip');
            $terms              = Input::get('payagreement');
            $ftlogin            = 0;

            if($terms=='on'){
                $terms = 1;
            }




            // Create a bronze User.
            $bronze = '88f76db3';

            // Request to Pap Merchants for the Payouts API function
            $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'loadPayouts', $session);


            // Checks if the user is an Older account Holder.
            if($user->oldUser===1){
                //Check to see if he user in PAP
                $email = $user->email;
                //$affiliate->setUserid();
                $affiliate->setNotificationEmail($email);

                //return Auth::user()->userid;



                // Try Loading the Affiliate Email
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                    //The User Does not exist
                    //return 'no account';
                     Log::error('postBronzePayment '.$e->getMessage());
                    return View::make('home')->with('global-warning','There was an error. Please sign up again.');
                }
                // Gather the User Id from the Located field
                $userId = $affiliate->getField('userid');


                // get the userid set the id with the current and define it.
                $request->setField('Id',$userId);




                // Send the request to change the the ID
                try {
                    $request->sendNow();

                } catch(Exception $e) {
                    Log::error('postBronzePayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }

                // Returns the Form with the changed ID
                $responseForm = $request->getForm();



                // Check if the Response form exist
                if ($responseForm->isSuccessful()) {
                    $minimumPayout = $responseForm->getFieldValue('minimumpayout');
                    $payoutOptionId = $responseForm->getFieldValue('payoutoptionid');

                }

                // Request another payout Form?
                $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'savePayouts', $session);


                // Request to Set the payouts with New options.
                $request->setField('Id',$userId);
                $request->setField('payoutoptionid', $payoutOptionId);
                $request->setField('code','');
                $request->setField('message','success');

                $request->setField('pp_email',$paypal_email);




                // Another reqest is neededs to
                // Git the Form userid from the pap Database
                $userId = $affiliate->getField('userid');

                // Get the Pap UserId and add the data to the New User
                $affiliate->setUserid($userId);

                // Try is the affiliate database can be load the affiliate
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                     Log::error('postBronzePayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }


                // Set the Id as the $userid *Created id.
                $request->setField('Id',$userId);


                // Send the Changes of the  Field
                try {
                    $request->sendNow();
                } catch(Exception $e) {
                    //die('API call error: '.$e->getMessage());
                     Log::error('postBronzePayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }




                // Request the Form into an Object
                $responseForm = $request->getForm();

                // If more options Place below




                //return [$payoutOptionId];

                // On the affiliate Database Define these parameters
                $affiliate->setStatus('A');
                $affiliate->setData(3, $street_address);
                $affiliate->setData(4,$city);
                $affiliate->setData(5,$state);
                //$affiliate->setData(6,'United States');
                $affiliate->setData(10,$paypal_email);
                $affiliate->setData(7,$zip);
                $affiliate->assignToPrivateCampaign($bronze);


                // Try to add the changes to the affiliate database
                try {
                    if ($affiliate->save()) {
                        //echo "Affiliate saved successfuly";

                    }
                } catch (Exception $e) {
                     Log::error('postBronzePayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');

                }

                // Make Changes to front Database Empty the old User Id and replace it with New Userid
                $user->userid = '';
                $user->userid = $userId;


                // This is the end of the First Request for the Old User
            }


            // If the User is new to the System
            else{
                //Check to see if he user in PAP
                $email = $user->email;
                $affiliate->setNotificationEmail($email);



                // Try Loading the Affiliate Email
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                     Log::error('postBronzePayment '.$e->getMessage());
                    //The User Does not exist
                    //return 'no account';
                }
                // Gather the User Id from the Located field
                $userId = $affiliate->getField('userid');


                // get the userid set the id with the current and define it.
                $request->setField('Id',$userId);




                // Send the request to change the the ID
                try {
                    $request->sendNow();

                } catch(Exception $e) {
                     Log::error('postBronzePayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal error - You can contact us or start over.');
                }

                // Returns the Form with the changed ID
                $responseForm = $request->getForm();



                // Check if the Response form exist
                if ($responseForm->isSuccessful()) {
                    $minimumPayout = $responseForm->getFieldValue('minimumpayout');
                    $payoutOptionId = $responseForm->getFieldValue('payoutoptionid');

                }

                // Request another payout Form?
                $request = new Gpf_Rpc_FormRequest('Pap_Merchants_User_AffiliateForm', 'savePayouts', $session);


                // Request to Set the payouts with New options.
                $request->setField('Id',$userId);
                $request->setField('payoutoptionid', $payoutOptionId);
                $request->setField('code','');
                $request->setField('message','success');

                $request->setField('pp_email',$paypal_email);




                // Another reqest is neededs to
                // Git the Form userid from the pap Database
                $userId = $affiliate->getField('userid');

                // Get the Pap UserId and add the data to the New User
                $affiliate->setUserid($userId);

                // Try is the affiliate database can be load the affiliate
                try {
                    $affiliate->load();
                } catch (Exception $e) {
                     Log::error('postBronzePayment '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');
                }


                // Set the Id as the $userid *Created id.
                $request->setField('Id',$userId);


                // Send the Changes of the  Field
                try {
                    $request->sendNow();
                } catch(Exception $e) {
                     Log::error('postBronzePayment '.$e->getMessage());
                    //die('API call error: '.$e->getMessage());
                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');
                }




                // Request the Form into an Object
                $responseForm = $request->getForm();

                // If more options Place below




                //return [$payoutOptionId];

                // On the affiliate Database Define these parameters
                $affiliate->setStatus('A');
                $affiliate->setData(3, $street_address);
                $affiliate->setData(4,$city);
                $affiliate->setData(5,$state);
                //$affiliate->setData(6,'United States');
                $affiliate->setData(10,$paypal_email);
                $affiliate->setData(7,$zip);
                $affiliate->assignToPrivateCampaign($bronze);


                // Try to add the changes to the affiliate database
                try {
                    if ($affiliate->save()) {
                        //echo "Affiliate saved successfuly";

                    }
                } catch (Exception $e) {
                     Log::error('postBronzePayment '.$e->getMessage());

                    return View::make('home')->with('global-danger','There is a internal Error - You can contact us or Start over.');

                }




                // This is the end of the First Request for the New User
            }



        };


        if($user){
            //$user->paypal_email = '';
            $user->paypal_email = $paypal_email;
            $user->street_address  = $street_address;
            $user->city         = $city;
            $user->state        = $state;
            $user->zip          = $zip;
            $user->terms        = $terms;
            $user->campaignid   = $bronze;
            $user->ftlogin      = $ftlogin;
            $user->oldUser= 0;
            $user->info         = 1;



            //Before Going to the platinum Home there should be a email or thank you indication after purchase.
            $pin = Auth::user()->pin;
            $firstName = Auth::user()->firstName;
            $username = Auth::user()->username;




            $pin = Auth::user()->pin;



            if($user->save()){

                Mail::send(
                    'emails.auth.pin', array(
                        'link'=>  $pin,
                        'firstName'=> $firstName,
                        'username' => $username),
                    function($message)use ($user){
                        $message->to($user->email, $user->username)->subject('Your Dialpad Pin');
                    });


                return Redirect::route('home')->with('global-confirmation','You are now a bronze member. Click on the "Dialpad" button to start earning AdDials credits.');
            };
        };
    }

    public function postSignIn(){
        //return 'post signin';
        $session = new Gpf_Api_Session("https://www.dial4dough.com/affiliates/scripts/server.php");
        $validator = Validator::make(Input::all(),User::$signinRules
            );
        if($validator-> fails()){
            return Redirect::back()
                        ->withErrors($validator)
                        ->withInput();
        }
        else{
           
            $remember = (Input::has('remember')) ? true : false;
            //attempt user signin.
            $auth = Auth::attempt(
                array(
                    'email'     => Input::get('email'),
                    'password'  => Input::get('password'),
                    'active'    => 1
                    ),$remember
                );
            if($auth){
                  //return Auth::user()->ftlogin;  
                //redirect to intended page
          if(Auth::user()->ftlogin === 1){
            
            return Redirect::route('welcome');
          }else{
            return Redirect::intended('/');
          }
                 //if(!$session->login(Input::get('email'),Input::get('password') , Gpf_Api_Session::AFFILIATE)) {
                //die("Cannot login. Message: ".$session->getMessage());
               //return Redirect::route('account-sign-in')
                   //->with('global-warning', $session->getMessage());
           //}
           //header('Location: '.$session->getUrlWithSessionInfo('http://www.dial4dough.com/affiliates/scripts/panel.php'));
                
            }
            else {
                return Redirect::back()
                        ->with('global-warning', 'Email or Password is incorrect, please try again.');
            }
        }
         return Redirect::back()
                ->with('global-warning', 'Either your email or Password is incorrect, please try again or request a new Password.');
    } //PostSign in
    /*Create a new user*/
    

    public function postChangePassword(){  
            
        $validator  = Validator::make(Input::all(),
            array(
                'old_password'  =>  'required',
                'password'      =>  'required|min:6',
                'password_again'=>  'required|same:password'
            )
        );
        if($validator ->fails()) {
            //redirect
            return Redirect::route('account-change-password')
                            ->withErrors($validator);
        }
        else{

            $user           = User::find(Auth::user()->id);

            $old_password   = Input::get('old_password');
            $password       = Input::get('password');
            $password_again = Input::get('password_again');
        
         }


        if(Hash::check($old_password, $user->getAuthPassword())){

            $user ->password = Hash::make($password);
            if($user->save()){
                return Redirect::route('settings')
                        ->with('global-success','Your password has been updated. ');
            }
        }
        else{
                return Redirect::route('settings')
                        ->with('global-danger', 'There was an input error: Please enter your current password.');

        }
        return Redirect::route('account-change-password')
                    ->with('global-danger', 'There was an Error check your password and try again..');  
    }
    
        
    public function postForgotPassword(){


                $validator = Validator::make(Input::all(),User::$emailOnly
                    );
                if($validator -> fails()) {
                    return Redirect::route('account-forgot-password')
                            ->withErrors($validator)
                            ->withInput();

                } else {


                    // change password

                    $user = User::where('email', '=',Input::get('email'))->first();


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

    public function postNewPassword(){




        $validator  = Validator::make(Input::all(),
            array(

                'password'      =>  'required|min:6',
                'password_again'=>  'required|same:password'
            )
        );


        if($validator ->fails()) {
            //redirect
            return Redirect::route('account-new-password')
                ->withErrors($validator);
        }
        else{

            $user = User::where('userid','=',Input::get('userid'))->first();
            $password       = Input::get('password');




            $user ->password = Hash::make($password);








            if($user->save()){
                return Redirect::route('account-sign-in')
                    ->with('global-success','Your password has been updated. You can log in with your new password. ');
            }

            else{
                return View::make('account.newpassword')
                    ->with('global-danger', 'There are no accounts with that email address. Please sign up.')->withInput();

            }

        }
    }





  
   
  


    public function subscribe()
    {
        $events->listen('user.create', 'UserEventSubscriber@onCreate');

        $events->listen('user.update', 'UserEventSubscriber@onUpdate');
    }
    
   

  






}
