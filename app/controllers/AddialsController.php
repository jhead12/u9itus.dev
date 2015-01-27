<?php
/**
 * Created by PhpStorm.
 * User: joshhead
 * Date: 1/26/15
 * Time: 3:18 PM
 */

class AddialsController extends \BaseController
{


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
        return View::make('adpad.index');
    }

    public function forgotPin()
    {

        $pin = mt_rand(0, 0x3fff | 0x800);

        $user = User::find(Auth::user()->id);
        $user->pin = $pin;

        //Cache::put('pin', $user);

        //Event to send out email
        //Event::


        if ($user->save()) {
            Mail::send(
                'emails.auth.pin', array(
                'link' => $pin,
                'firstName' => Auth::user()->firstName,
                'username' => Auth::user()->username),
                function ($message) use ($user) {
                    $message->to($user->email, $user->username)->subject('Here is your new pin');
                });

            return Redirect::route('dialpad')->with('global-success', ' A new pin has been sent to your email address.');

        }


    }

    public function create()
    {


        $list = Addial::$list;
        //Get Shopify Info

        //$shopify = Shopify::all();
        $shopify = array('id' => 'test', 'order_id' => '22992');
        //Implement an Interface for the Shopify and Marketer


        return View::make('adpad.adform')->with('value', $shopify)->with('list', $list);


    }


    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {


        $validator = Validator::make(Input::all(), Addial::$form);
        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator)->withInput();

        } else {

            $title = Input::get('title');
            $telephone = Input::get('telephone');
            $company_name = Input::get('company_name');
            $postingbody = Input::get('postingbody');
            $address = Input::get('company_address');
            $userId = Input::get('userid');


            //Move to a path on the database
            if (Input::hasFile('audio_file')) {

                $audio_file = Input::file('audio_file');


                try {

                    $name = time() . '-' . '-' . $audio_file->getClientOriginalName();

                    $audio = $audio_file->move(public_path() . '/assets/addials/', $name);

                } catch (Exception $e) {

                    return $e->getMessage();
                }
            } else {
                $name = null;
            }


            $amount = intval(Input::get('amount'));
            $company_address = Input::get('company_address');
            $accountSid = Crypt::encrypt(Input::get('accountSid'));
            $authToken = Crypt::encrypt(Input::get('authToken'));
            $type = Input::get('type');
            $catagory = Input::get('catagory');
            $purchase_url = Input::get('purchase_url');


            $marketer = Marketer::create(
                [
                    'title' => $title,
                    'telephone' => $telephone,
                    'company_name' => $company_name,
                    // 'banners'        => $image,

                    'postingbody' => $postingbody,
                    'company_name' => $company_name,

                    'catagory' => $catagory,
                    'company_address' => $company_address,

                    'audio_file' => $name,

                    'id' => $userId,

                    'purchase_url' => $purchase_url,

                    'amount' => $amount,

                    // 'products'       =>  'Products',
                    'type' => $type,
                    'accountSid' => $accountSid,
                    'authToken' => $authToken


                ]);

            $marketer->save();

            //Event::fire('addial.submit');


            return Redirect::back()->with('global-success', 'The Addial was created');


        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function show($id)
    {


        //This Opens the Modal with the Marketer information


        $str_id = intval($id);

        $marketer = Marketer::where('id', '=', $id)->get();


        Event::fire('addial.show', $marketer);


        Session::push('merid', $id);


        //return $marketer;


        return View::make('account.modals.addials')->with('marketer', $marketer);


    }

    /**
     * AdDial Confirmation
     *
     * @param  int $id
     * @return Response
     */
    public function confirm()
    {
        //Addial-confirm

        $data = Input::all();
        $marketers = Marketer::all();

        //
        if ($data['pin'] === Auth::user()->pin) {
            $id = $data['currentId'];
            //$id = $data['merchid'];

            $merchid = Session::flash('current', $id);

            //Event
            $marketer = Marketer::find($id);
            $amount = $marketer->amount;
            //If marketer exist Create an Addials and update the amount of "Addials" in the marketer database
            if ($marketer) {
                $ads = $marketer->amount;
                $serial = $userID = str_random(15);
                $id = $marketer->_id;
                //If the marketer doesn't have any more addials
                if ($amount === 0) {
                    //Marketer::destroy($id);

                    //can a flash message be created.
                    return Redirect::back()->with('global-warning', 'This AdDial campaign is completed.You will not earn credit for this campaign.');
                } else {

                    //Fire an Event to hand the addial creation
                    $addial = Addial::create(array(
                        'id' => $serial,
                        'user' => $marketer->company_name,
                        'campainId' => $marketer->_id,
                        'currentAmount' => $marketer->amount,
                        'beforeAmount' => $ads,
                        'userclickId' => Auth::user()->userid,
                        'completed' => false
                    ));
                }
                //return View::make('account.addials.business')->with('marketer',$marketer);
                $decrement = $marketer->decrement('amount');
                if ($addial) {
                    $saleTracker = new Pap_Api_SaleTracker('http://dialer.dial4dough.com/scripts/sale.php');
                    $saleTracker->setAccountId('default1');
                    $saleTracker->setVisitorId(Auth::user()->userid);
                    $sale2 = $saleTracker->createSale();
                    $sale2->setAffiliateID(Auth::user()->userid);
                    $sale2->setCampaignID(Auth::user()->campaignid);
                    $sale2->setOrderID($serial);
                    $sale2->setTotalCost('1.75');
                    $saleTracker->register();
                    //return  $marketer;
                    return View::make('account.addials.business')->with('marketer', $marketer);
                };
            };
        } else {
            return '<h3 id="error"> You have entered an incorrect pin. Please try again or reset <a class="button" href="/forgot-pin">Reset</a></h3>';


        }


        //Marketer::find('id','=',$str_id)->decrement('addials',$amount);
        //Update Timestamp
        //Marketer::save();
    }


    public function getPhoneStatus()
    {

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


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }


    /**a
     * Update the specified resource in storage.
     *
     * @param  int $id
     * @return Response
     */
    public function update($id)
    {
        //
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }


        public function getDialpad()
        {
            $cookies =  array(Request::server('HTTP_COOKIE'));

//            foreach($cookies as $value){
//                    //return  "Value: $value<br />\n";
//
//
//                  $array = explode(';', $value);
//                $new_array = array();
//                array_walk($array,'walk', $new_array);
//
//                function walk($val, $key, $new_array){
//                    $nums = explode('=',$val);
//                    $new_array[$nums[0]] = $nums[1];
//                }
//                return $new_array;
//
//
//            }

            //Get the Id from the PAP User ID.
            $userid = 'visitorID';
            //If there is an user Addial that has a completed status of true. Then take the addial off the dialpad.Other wise show it.
            //$addials = Addial::where('userclickId', '=', $userid)->where('completed', '=', false)->remember(60)->get();
            $addials = Addial::all();

            //$notCompleted = Addial::where('userclickId','=',$userid)->where('completed','=',false)->distinct()->get(array('campaignId'));
            $marketers = Marketer::where('amount', '>', 0)->simplePaginate(5);
            //Check if Uncompleted Addial Still has a related campaign. If not Destroy Addial.

            if ($addials || $marketers) {
                //return 'yes';
                $_marketer = Marketer::distinct()->get(array('orderId'));
                $_addials = Addial::distinct()->get(array('orderId'));
                for ($i = 0; $i < count($_addials); ++$i) {
                    $_id = $_addials[$i];
                    $marketers = Marketer::where('orderId', '!=', $_id[$i])->remember(60)->get();
                }

                return View::make('adpad.user')->with('marketers', $marketers);
            } else {

                $marketers = Marketer::remember(60);
                return View::make('adpad.user')->with('marketers', $marketers);
            }
            return View::make('home')->with('global-danger', 'There was a problem, please try again. If problem persist please contact Dial4dough');

    }
}