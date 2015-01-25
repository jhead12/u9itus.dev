<?php

Route::get('/terms',[
		'as' 	=> 'terms',
		'uses'	=> 'PageController@terms'
	]);

Route::get('/privatepolicy',[
		'as'	=> 'privatepolicy',
		'uses'	=>  'PageController@privacy'

	]);