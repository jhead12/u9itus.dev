<?php

Route::get('/terms',[
		'as' 	=> 'terms',
		'uses'	=> 'PagesController@terms'
	]);

Route::get('/privatepolicy',[
		'as'	=> 'privatepolicy',
		'uses'	=>  'PagesController@private'

	]);