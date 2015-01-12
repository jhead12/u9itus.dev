<?php namespace D4D\PAP;

class PapRepository inplements PapRepositoryInterface {

	protected $session;
	
	public function _construct()
	{
	 $session = new Gpf_Api_Session("http://dialer.dial4dough.com/scripts/server.php");
            
            if(!@$session->login("matrixblend@yahoo.com", "mc1282")) {
            Log::error("Cannot login. Message: ".$session->getMessage());
            }
	}

	public function getAll()
	{
		
	  //----------------------------------------------
		// get recordset with list of affiliates

		$request = new Pap_Api_AffiliatesGrid($session);
		//Filtering affiliate with username affiliate@example.com  - Filters are not mandatory
	//	$request->addFilter('username', Gpf_Data_Filter::EQUALS, 'affiliate@example.com');

	// sets limit to 30 rows, offset to 0 (first row starts)
	$request->setLimit(0, 30);

	// sets columns, use it only if you want retrieve other as default columns
	$request->addParam('columns', new Gpf_Rpc_Array(array(array('id'), array('refid'), array('userid'), 
	array('username'), array('firstname'), array('lastname'), array('rstatus'), array('parentuserid'),
	array('dateinserted'), array('salesCount'), array('clicksRaw'), array('clicksUnique'))));

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

		
	
		return $users = [
			'userid'    	=> $rec->get('userid'),
			'firstname' 	=> $rec->get('firstname'),
			'lastname'	=> rec->get('lastname')
		];


      }	  
   }
}
}

	
	public function find($id)
	{
		//Will return the order by id
		return Order::findOrFail($id);
	}






}
