<?php
use Zizaco\Confide\ConfideUser;
use Zizaco\Confide\ConfideUserInterface;
use Zizaco\Entrust\HasRole;

use Laravel\Cashier\BillableTrait;
use Laravel\Cashier\BillableInterface;


class User extends Eloquent implements  BillableInterface, ConfideUserInterface {


use ConfideUser, BillableTrait;
    use HasRole;


	protected $fillable = array('pin','refid','firstName','lastName','sex','country','birthday','oldUser','ftlogin','Paypal_email','telephone','ip_address','comments','ratings','favorites','email', 'username', 'password','password_temp','street_address','city','state','zip', 'code', 'active', 'userid','terms', 'campaignid');

	public static $signinRules = [
			'email' => 'required',
			'password'	=> 'required'

	];

    /**
     * Redirect after auth.
     * If ifValid is set to true it will redirect a logged in user.
     * @param $redirect
     * @param bool $ifValid
     * @return mixed
     */
    public static function checkAuthAndRedirect($redirect, $ifValid=false)
    {
        // Get the user information
        $user = Auth::user();
        $redirectTo = false;

        if(empty($user->id) && ! $ifValid) // Not logged in redirect, set session.
        {
            Session::put('loginRedirect', $redirect);
            $redirectTo = Redirect::to('user/login')
                ->with( 'notice', Lang::get('user/user.login_first') );
        }
        elseif(!empty($user->id) && $ifValid) // Valid user, we want to redirect.
        {
            $redirectTo = Redirect::to($redirect);
        }

        return array($user, $redirectTo);
    }

    public function currentUser()
    {
        return Confide::user();
    }

    /**
     * Get the e-mail address where password reminders are sent.
     *
     * @return string
     */
    public function getReminderEmail()
    {
        return $this->email;
    }

    /**
     * Returns user's current role ids only.
     * @return array|bool
     */
    public function currentRoleIds()
    {
        $roles = $this->roles;
        $roleIds = false;
        if( !empty( $roles ) ) {
            $roleIds = array();
            foreach( $roles as &$role )
            {
                $roleIds[] = $role->id;
            }
        }
        return $roleIds;
    }


    /**
     * Save roles inputted from multiselect
     * @param $inputRoles
     */
    public function saveRoles($inputRoles)
    {
        if(! empty($inputRoles)) {
            $this->roles()->sync($inputRoles);
        } else {
            $this->roles()->detach();
        }
    }

    /**
     * Get the date the user was created.
     *
     * @return string
     */
    public function joined()
    {
        return String::date(Carbon::createFromFormat('Y-n-j G:i:s', $this->created_at));
    }


    /**
     * Get user by username
     * @param $username
     * @return mixed
     */
    public function getUserByUsername( $username )
    {
        return $this->where('username', '=', $username)->first();
    }
    public static $emailOnly =[
                'email' => 'require'
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
                'country2'        => 'required'


	];
	public static $payRules = [

				'paypal_email'      => 'required|max:50| unique:users',
                'street_address'    => 'required |max:50',
                'city'              => 'required|max:50',
                'state'             => 'required|max:20|min:2',
                'zip'               => 'required|max:5',
                'payagreement'      => 'required'

	];

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



}
