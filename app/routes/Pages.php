<?php

Route::get('/','PageController@home');
Route::get('/',[
	'as'	=> 'home',
	'uses'	=> 'PageController@home'

	]);

Route::get('/about','PageController@about');

Route::get('/pricing','PageController@pricing');

Route::get('/thankyou', [
	'as'	=> 'thankyou',
	'uses'	=> 'PageController@thankyou'
	]);