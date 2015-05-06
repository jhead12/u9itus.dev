<?php

Route::get('/','PageController@home');
Route::get('/',[
	'as'	=> 'home',
	'uses'	=> 'PageController@home'

	]);

Route::get('about','PageController@about');

Route::get('pricing','PageController@pricing');

Route::get('upgrade',function()
{

	return View::make('pages/upgrade');
});



# Filter for detect language
Route::when('contact-us','detectLang');


# Contact Us Static Page
Route::get('contact', function()
{
	// Return about us page
	return View::make('pages/site/contact-us');
});

Route::get('polidream', function()
{
	// Return about us page
	return View::make('pages/site/polidream');
});

Route::get('about', function(){

	return View::make('pages/site/about');
});