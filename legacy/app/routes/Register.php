<?php

/*
* Registration!
*/
Route::get('/register', [
	'as' 	=> 'register_path',
	'uses'	=> 'RegistrationController@create'
	]);
Route::post('/register',[
	'before' => 'csrf',
	'as'	=> 'register_path',
	'uses'	=> 'RegistrationController@store'

	]);