<?php
/**
 * Created by PhpStorm.
 * User: joshhead
 * Date: 2/2/15
 * Time: 10:23 AM
 */
namespace D4D\Repos\Pap;

use Gpf_Api_Session;
use Gpf_Data_Record;
use Pap_Api_SaleTracker;
use Gpf_Rpc_GridRequest;
use Pap_Api_AffiliatesGrid;
use Gpf_Data_Filter;
use Gpf_Rpc_Array;
use Illuminate\Support\Facades\Redirect;

class DbPapRepository implements PapRepositoryInterface{

    public $session;

    function __construct(){

        $session = new Gpf_Api_Session("https://www.dialer.dial4dough.com/scripts/server.php");
        if(!$session->login(getenv('PAP_USERNAME'),getenv('PAP_PASSWORD') , Gpf_Api_Session::MERCHANT)) {
            die("Cannot login. Message: ".$session->getMessage());
        }
        //header('Location: '.$session->getUrlWithSessionInfo('http://www.dial4dough.com/affiliates/scripts/panel.php'));


        $this->session = $session;

    }
    public function getAll(){}


    public function getUser(){

        $online = $this->displayOnlineUsers();

        $visitorsId =  $_COOKIE['PAPVisitorId'];

        //dd($online);

        $request = new Pap_Api_AffiliatesGrid($this->session);
        foreach([$online] as $user)
        {
            //Filtering affiliate with username affiliate@example.com  - Filters are not mandatory
            //$request->addFilter($user['username'], Gpf_Data_Filter::EQUALS, $user['username']);
        }




        // sets limit to 30 rows, offset to 0 (first row starts)
        $request->setLimit(0, 30);

        // sets columns, use it only if you want retrieve other as default columns
        $request->addParam('columns', new Gpf_Rpc_Array(array(array('id'), array('refid'), array('userid'),
            array('username'), array('firstname'), array('lastname'), array('rstatus'), array('parentuserid'),
            array('dateinserted'), array('salesCount'), array('clicksRaw'), array('clicksUnique'))));


        // add filter for specific visitorId and Actual type
        $request->addFilter("visitorid", "=", $visitorsId);
        $request->addFilter("rtype", "=", "A");

        // send request
        try {
            $request->sendNow();
        } catch(Exception $e) {
            die("API call error: ".$e->getMessage());
        }

       // request was successful, get the grid result
        $grid = $request->getGrid();

        // get recordset from the grid
        $recordset = $grid->getRecordset();

         // iterate through the records
        foreach($recordset as $rec) {
            return  'Affiliate userid: '.$rec->get('userid').', Affiliate name: '.$rec->get('firstname').' '.$rec->get('lastname').'<br>';
        }


       //----------------------------------------------
       // in case there are more than 30 records in total,
       // we should load and display the rest of the records
        // via the cycle below

        $totalRecords = $grid->getTotalCount();
        $maxRecords = $recordset->getSize();
        if ($maxRecords != 0) {
            $cycles = ceil($totalRecords / $maxRecords);
            for($i=1; $i<$cycles; $i++) {
                // now get next 30 records
                $request->setLimit($i * $maxRecords, $maxRecords);
                $request->sendNow();
                $recordset = $request->getGrid()->getRecordset();
                // iterate through the records
                foreach($recordset as $rec) {
                    echo 'Affiliate userid: '.$rec->get('userid').', Affiliate name: '.$rec->get('firstname').' '.$rec->get('lastname').'<br>';
                }
            }
        }
    }

    public function getVisitorId(){


       $Id =  $_COOKIE['PAPVisitorId'];

        $visitorId = $Id; //you should obtain the PAPVistorId value dynamically, e.g. via $_COOKIE or $_GET or $_POST parameter

        if (strlen($visitorId) > 32) {
            $visitorId = substr($visitorId, -32);
        }

        $request = new Gpf_Rpc_GridRequest('Pap_Merchants_Tools_VisitorAffiliatesGrid', 'getRows', $this->session);
        // set filter
        $request->addFilter("visitorid", Gpf_Data_Filter::EQUALS, $visitorId);
        $request->addFilter("rtype", Gpf_Data_Filter::EQUALS, 'A');
        $request->addFilter("validto", Gpf_Data_Filter::DATE_EQUALS_GREATER, date('Y-m-d'));
        //in PAN insert here your merchant network accountid
        //$request->addFilter("accountid", Gpf_Data_Filter::EQUALS, 'default1');
        $request->setLimit(0, 1);


        try {
            $request->sendNow();
        } catch(Exception $e) {
            die("API call error: ".$e->getMessage());
        }

        $grid = $request->getGrid();


        $recordset = $grid->getRecordset();


        if ($recordset->getSize() > 0) {
            $affid = $recordset->get(0)->get('userid');

            return $affid;

        }else{
           //$this->displayOnlineUsers();
            return null;
        }


    }
    public function displayOnlineUsers(){

        $request = new Gpf_Rpc_Gridrequest("Gpf_Report_OnlineUsers", "getRows", $this->session);

        // send request
        try {
            $request->sendNow();
        } catch(Exception $e) {
            die("API call error: ".$e->getMessage());
        }

        // request was successful, get the grid result
        $grid = $request->getGrid();

        // get recordset from the grid
        $recordset = $grid->getRecordset();

        foreach($recordset as $rec) {
            //echo 'Name: '.$rec->get('firstname').' '.$rec->get('lastname').' Username: '.$rec->get('username').'<br>';

            return ['name'=> $rec->get('firstname'), 'username'=>$rec->get('username')];
        }



    }

    public function adCommission($user, $id){

        $serial = str_random(6);


        $saleTracker = new Pap_Api_SaleTracker('https://www.dialer.dial4dough.com/scripts/sale.php');
        $saleTracker->setAccountId('default1');

        $saleTracker->setVisitorId($user);
        $sale2 = $saleTracker->createSale();
        //$sale2->setAffiliateID(Auth::user()->userid);
        //$sale2->setCampaignID(Auth::user()->campaignid);
        $sale2->setOrderID($serial);
        $sale2->setProductID($id);
        $sale2->setTotalCost('1.75');
        $saleTracker->register();
    }





}