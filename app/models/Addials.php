<?php

use Jenssegers\Mongodb\Eloquent\SoftDeletingTrait;
use Jenssegers\Mongodb\Model as Eloquent;

class Addial extends Eloquent {

    use SoftDeletingTrait;

    protected $fillable = array('serial','currentAmount','userclickId','campaignId','addials','firstName','id','lastname','audio_file','city','company_name','created_at','email','postingbody','updated_at','completed');


    //protected $table = 'addials';
    public function user(){
        return $this->belongsToMany('Marketer','User',null);
    }

    
    protected $connection = 'mongodb';

    protected $collection = 'addials';
    protected $dates = ['deleted_at'];


}
//This will access the addial serial creator Controller.
//The addial creator will serialize opened addials
