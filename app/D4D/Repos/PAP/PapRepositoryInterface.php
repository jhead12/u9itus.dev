<?php namespace D4D\Repos\Pap;

interface PapRepositoryInterface {


	public function getAll();
	public function getVisitorId();
	public function displayOnlineUsers();
	public function adCommission($user, $id);


}