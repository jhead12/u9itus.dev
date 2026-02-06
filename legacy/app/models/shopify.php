<?php

use Jenssegers\Mongodb\Eloquent\SoftDeletingTrait;
use Jenssegers\Mongodb\Model as Eloquent;

class Shopify extends Eloquent {

    use SoftDeletingTrait;

    //protected $fillable = array('serial','currentAmount','userclickId','addials','firstName','id','lastname','audio_file','city','company_name','created_at','email','postingbody','updated_at','completed');
    	//Use this file to access all the shopify orders and create a Shopify::all(); function


    
    protected $connection = 'mongodb2';

    protected $collection = 'users';
    //protected $dates = ['deleted_at'];


}
//This will access the addial serial creator Controller.
//The addial creator will serialize opened addials
