<?php

use Illuminate\Database\Eloquent\SoftDeletingTrait;
use Jenssegers\Mongodb\Model as Eloquent;
class Marketer extends Eloquent {

	protected $fillable = array('firstName','lastName','ftlogin','Paypal_email','telephone','ip_address','comments','ratings','favorites','email', 'username','company_name','addials', 'password','password_temp','street_address','city','state','zip', 'code', 'active', 'userid', 'postingtitle','postingbody','audio_file', 'id');

	use SoftDeletingTrait;
	/**
	 * The database table used by the model.
	 *
	 * @var string,
	 */
    protected $connection = 'mongodb';

    protected $collection ='articles';
	protected $dates = ['deleted_at'];




  

}
