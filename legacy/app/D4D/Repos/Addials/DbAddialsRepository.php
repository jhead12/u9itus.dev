<?php
/**
 * Created by PhpStorm.
 * User: joshhead
 * Date: 2/2/15
 * Time: 10:23 AM
 */
namespace D4D\Repos\Addials;

use Addial;


use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Marketer;


class DbAddialsRepository  implements AddialsRepositoryInterface {

    private $addial;
    private $marketer;

    function __construct(Addial $addial, Marketer $marketer){
        $this->addial = $addial;
        $this->marketer = $marketer;

    }

    public function getAll(){


        return $this->addial->all();


    }
    public function filter($key){

        //return $key;
        //return $_COOKIE['88c6adcf1679e42dab512b8c784a0c28b62456b0'];
    }
    public function getDials(){


        //$ip = Request::getClientIp();
        $ip = '';






        //$id = Cookie::get('key');
         $_addials = $this->addial->where('gsx$ip','!=',$ip)->get();
        return $_marketer = $this->marketer->where('amount', '>', 0)->get();

        $qry = array();

        if($_addials || $_marketer )
        {
            $qry['gsx$productid'] = array('');
            for ($i = 0; $i <= count($_addials) - 1; $i++) {
                  $_id = $_addials [$i];

                 return $marketers[$i] = $this->marketer->where('id', $_id[$i])->get();



                return $marketers[$i];
            }



        }






    }

    /**
     * @param $id
     * @return mixed
     */
    public function getById($id){



      return  $this->marketer->where('id', '=', $id)->get();


    }



}