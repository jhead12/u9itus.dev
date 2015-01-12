<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;
use Laravel\Cashier\BillableTrait;
use Laravel\Cashier\BillableInterface;


class User extends Eloquent implements UserInterface, RemindableInterface, BillableInterface {


use UserTrait, RemindableTrait, BillableTrait;

public $errors;

	protected $fillable = array('pin','refid','firstName','lastName','sex','country','birthday','oldUser','ftlogin','Paypal_email','telephone','ip_address','comments','ratings','favorites','email', 'username', 'password','password_temp','street_address','city','state','zip', 'code', 'active', 'userid','terms', 'campaignid');

	public static $signinRules = [
			'email' => 'required',
			'password'	=> 'required'

	];

	public static $rules = [

	 			'firstName'      => 'required|max:50| min:2',
                'lastName'       => 'required |max:50',
                'telephone'      => 'required|unique:users',
                'email'          => 'required | max:60|unique:users',
                'terms'          => 'required',
                'password'       => 'required',
                'username'       => 'required|max:20|min:3|unique:users',
                'sex'            => 'required',
                //'country'        => 'required'


	];
	public static $payRules = [

				'paypal_email'      => 'required|max:50| unique:users',
                'street_address'    => 'required |max:50',
                'city'              => 'required|max:50',
                'state'             => 'required|max:20|min:2',
                'zip'               => 'required|max:5',
                'payagreement'      => 'required'

	];
	public static $emailOnly = [ 'email' => 'required'];
	public static $payRulesExt = [

				'paypal_email'      => 'required|max:50| unique:users',
                'street_address'    => 'required |max:50',
                'city'              => 'required|max:50',
                'state'             => 'required|max:20|min:2',
                'zip'               => 'required|max:5',
                'name'              => 'required',
                'card'              => 'required',
                'cvc'               => 'required',
                'exp-month'         => 'required',
                'exp-year'          => 'required',
                'payagreement'      => 'required'


	];

	public static $platinumMem  = '938b2b84';
	public static $goldMem		= 'faa06c36';
	public static $bronzeMem	= '88f76db3';
	public static $payNopaypal = [

		
                'street_address'    => 'required |max:50',
                'city'              => 'required|max:50',
                'state'             => 'required|max:20|min:2',
                'zip'               => 'required|max:5',
                'name'              => 'required',
                'card'              => 'required',
                'cvc'               => 'required',
                'exp-month'         => 'required',
                'exp-year'          => 'required',
                'payagreement'      => 'required'


	];

	

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'users';
	
	


	/*
	|Cashier
	* @var array
	*/
	protected $dates = ['trial_ends_at', 'subscription_ends_at'];


	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array('password');

	public function isValid($data)
	{

		$validation = Validator::make($data, [
			'rules'		=> static::$rules,
			'payRulesExt'=>static::$payRulesExt,
			'payRules'	=> static::$payRules

			]);

		if ($validation->passes())
		{
			return true;
		}

		$this->$errors = $validation->messages();
		return false;
	}

}
