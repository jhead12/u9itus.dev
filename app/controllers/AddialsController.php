<?php
/**
 * Created by PhpStorm.
 * User: joshhead
 * Date: 1/26/15
 * Time: 3:18 PM
 */


use D4D\Repos\Addials\AddialsRepositoryInterface;
use D4D\Repos\Pap\PapRepositoryInterface;
use Illuminate\Support\Facades\Cookie;
use Laracasts\Utilities\JavaScript\Facades\JavaScript;


class AddialsController extends \BaseController
{
    private $papRepo;
    private $adRepo;

    private $marketer;


    /**
     * @param PapRepositoryInterface $papRepo
     * @param AddialsRepositoryInterface $adRepo
     * @param Marketer $marketer
     */
    function __construct(PapRepositoryInterface $papRepo, AddialsRepositoryInterface $adRepo, Marketer $marketer)
    {
        $this->papRepo = $papRepo;

        $this->adRepo = $adRepo;
        $this->marketer = $marketer;

    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {



        $marketer=  $this->marketer->all();

        $id = str_random(6);
        return View::make('adpad.index')->with('marketers', $marketer );
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


            $marketer = $this->marketer->create(
                [
                    'title' => $title,
                    'telephone' => $telephone,

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

            $this->marketer->save();

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


        $marketer =$this->adRepo->getById($id);

       //Cookie::queue('key', $id, 10000);

        //Session::put('key', $id );

        return View::make('pages.addials.business')->with('marketer',$marketer);


    }

    public function thankyou(){

        //Get the id of the Id of the current Item.
        $id = Cookie::get('key');

        //$this->adRepo->filter($id);

        $sid = str_random(10);


        //$this->papRepo->adCommission($user, $id);
        $affid = $this->papRepo->getVisitorId();
        $visitorid = $_COOKIE['PAPVisitorId'];


        //dd($this->papRepo->getUser());

        Javascript::put(['id'=>['name'=>$id, 'visitorid'=>$visitorid,'affid'=>$affid, 'id'=>$sid]]);

        //Cookie::forever($sid, $id);

        //Session::forget('key');

        Cookie::forget('key');




        return view('adpad.thankyou');
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
        $marketers = $this->marketer->all();

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
                $decrement = $this->marketer->decrement('amount');
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
     * @return mixed
     */
    public function getDialpad()
        {

            //return Cookie::get('7654389');

            $marketers = $this->adRepo->getDials();
            // $this->adRepo->filter($marketers);





                //Javascript::put(['marketers'=>'marketer']);


                return View::make('adpad.user')->with('marketers', $marketers);


    }
}