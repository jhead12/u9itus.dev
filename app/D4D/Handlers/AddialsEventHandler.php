<?php namespace D4D\Handlers;

use D4D\Repositories\OrderRepositoryInterface;

class AddialsEventHandler {

	protected $marketer;

	public function _contruct(OrderRespositoryInterface $marketer){

		$this->marketer = $marketer;

	}

	public function index()
	{
		$this->marketer = $this->marketer->getAll();
	}
	public function AddialTracker($id){

		dd('The Id that is coming in:'.$id );
	}

	public function onCompleted()
	{
		dd('An Addial has been completed by current user ');
		//send an email to the marketer - to purchase more addials
		// remove from the addials list 
		//place into the advertisers cornor for 32 days.
		//create an event for the adCornor.

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
                    
                };

                //$addial->completed = true;
                //$addial->save();

	}
	public function onCall()
	{
		dd('This will connect to the api with the user sid');

	}

	public function submit()
	{
		//dd('This will connect to the api with the user sid');
		Mail::send('emails.auth.status', array('url'=>'', 'username'=>'John Head', 'status'=>'Test'),function($message)use($user){
                            $message->to('josh.head12@gmail.com')->subject('An Addial was Created');

                        });

	}
	public function create($marketer)
	{
		          
             		

		 $addial = Addial::create(array(
                        'id' => $serial,
                        'user' => $marketer->company_name,
                        'campainId'     => $marketer->_id,
                        'currentAmount' => $marketer->amount,
                        'beforeAmount' => $ads,
                        'userclickId' => Auth::user()->userid,
                        'completed'     => false
                    ));

		 Mail::send('emails.auth.status', array('url'=>'', 'username'=>'John Head', 'status'=>$addial),function($message)use($user){
                            $message->to('josh.head12@gmail.com')->subject('An Addial was accessed');

                        });
       

  		        
		//Send addial to the database.


	}
	public function onShow()
	{
		    
		//$marketer->toArray();


	}	

	public function onLogin()
	{
		    
		//dd('this is running the login event');
		//check the vistorID and see if it matches the pap


	}

	public function doneStatus()
	{
		dd('update the list with the proper color indicating completion of addial.');
	}

	public function subscribe($events)
	{
		$events->listen('addial.completed',
			'D4D\Handlers\AddialsEventHandler@onCompleted');
		$events->listen('addial.call','D4D\Handlers\AddialsEventHandler@onCompleted');
		$events->listen('addial.create','D4D\Handlers\AddialsEventHandler@create');
		$events->listen('addial.show','D4D\Handlers\AddialsEventHandler@onShow');
		$events->listen('addial.submit','D4D\Handlers\AddialsEventHandler@onShow');
		$events->listen('addial.login','D4D\Handlers\AddialsEventHandler@onLogin');
		$events->listen('addial.track','D4D\Handlers\AddialsEventHandler@AddialTracker');

	}




}
