<?php

use Jenssegers\Mongodb\Eloquent\SoftDeletingTrait;

use Jenssegers\Mongodb\Model as Eloquent;

class Addial extends Eloquent {

    use SoftDeletingTrait;

    protected $fillable = array(
    'status','form_id','fields','flag','ip','id' );


    //protected $table = 'addials';
//    public function user(){
//        return $this->belongsToMany('Marketer','User',null);
//    }


    protected $connection = 'mongodb';

    protected $collection = 'addials';
    //protected $dates = ['updated'];


    public static $form =[
        // 'title'         => 'required|max:50| min:2',
        // 'telephone'     => 'required |max:50',
        // 'company_name'  => 'required| max:50',
        // 'purchase_url'  => 'required',
        // 'type'          => 'required',
        // 'authToken'     => 'required',
        // 'accountSid'    => 'required'

    ];

    public static $adVal =[

        'pin'       => 'required|unique:users'




    ];




    public static $list = [

        'antiques',
        'appliances',
        'arts+crafts',
        'atv/utv/sno',
        'auto parts',
        'baby+kid',
        'barter',
        'beauty+hlth',
        'bikes',
        'boats',
        'books',
        'business',
        'cars+trucks',
        'cds/dvd/vhs',
        'cell phones',
        'clothes+acc',
        'collectibles',
        'computers',
        'electronics',

        'farm+garden',
        'free',
        'furniture',
        'garage sale',
        'general',
        'heavy equip',
        'household',
        'jewelry',
        'materials',
        'motorcycles',
        'music instr',
        'photo+video',
        'rvs+camp',
        'sporting',
        'tickets',
        'tools',
        'toys+games',
        'video gaming',
        'wanted'

    ];






}
//This will access the addial serial creator Controller.
//The addial creator will serialize opened addials