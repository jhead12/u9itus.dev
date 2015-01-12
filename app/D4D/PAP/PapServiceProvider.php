<?php namespace D4D\PAP;


use Illuminate\Support\ServiceProvider;
use Config;


class PapServiceProvider extends ServiceProvider {


		public function register(){

			$this->app->bind('pap',function(){

			$session = new Gpf_Api_Session("https://www.dial4dough.com/affiliates/scripts/server.php");

				$session->login(Config::get('pap.username'), Config::get('pap.password'));

			

			return new PapApi($session);



			});

		}
		  public function postBronzePayment(){

		  		

        $session = new Gpf_Api_Session("http://dialer.dial4dough.com/scripts/server.php");
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
                    dd($affiliate->load());
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
                    return View::back()->with('global-danger','There is a internal error - You can contact us or start over.');
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


                return Redirect::route('home')->with('global-success','You are now a bronze member. Click on the "Dialpad" button to start earning AdDials credits.');
            };
        };
    }

    



}