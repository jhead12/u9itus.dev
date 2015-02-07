<?php namespace D4D\Repos\Addials;

interface AddialsRepositoryInterface {


	public function getAll();
	public function getDials();
	public function getById($id);
	public function filter($key);


}