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



# Filter for detect language
Route::when('contact-us','detectLang');

# Contact Us Static Page
Route::get('contact-us', function()
{
	// Return about us page
	return View::make('pages/site/contact-us');
});