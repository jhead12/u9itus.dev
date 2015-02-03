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
use Gpf_Data_Filter;
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


    public function getAll(){

        return 'getting all from the pap baby';
    }

    public function getVisitorId(){


      return  $visitorId =  $_COOKIE['PAPVisitorId'];
        if($visitorId !=null){



            if (strlen($visitorId) > 32) {
                $visitorId = substr($visitorId, -32);
            }

            $request = new Gpf_Rpc_GridRequest('Pap_Merchants_Tools_VisitorAffiliatesGrid', 'getRows', $this->session);

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
                echo $recordset->get(0)->get('userid');
            }






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

    public function adCommission($user){

        $serial = str_random(6);


        $saleTracker = new Pap_Api_SaleTracker('https://www.dialer.dial4dough.com/scripts/sale.php');
        $saleTracker->setAccountId('default1');
        $saleTracker->setVisitorId( $this->getVisitorId());
        $sale2 = $saleTracker->createSale();
        //$sale2->setAffiliateID(Auth::user()->userid);
        //$sale2->setCampaignID(Auth::user()->campaignid);
        $sale2->setOrderID($serial);
        $sale2->setTotalCost('1.75');
        $saleTracker->register();
    }





}