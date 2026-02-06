<?php namespace D4D\Addials;

class PostAddialListingCommand{

	Public $amount;
    public $accountSid; 
    public $authToken; 
    public $type;
    public $catagory;
    public $purchase_url; 

	public $title;

	public $description;

	function _construct($accountSid, $title)
	{
		$this->accountSid = $accountSid;
		$this->title = $title;

		//Need to add all the fields from the addials controller
	}
}