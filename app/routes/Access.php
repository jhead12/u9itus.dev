<?php

Route::resource('access', 'AccessController');

 //General Routes
/*
| Sign out
*/
Route::get('/logout',array(
	  'before' => 'Auth',
  'as' => 'logout',
  'uses' => 'AccountController@getSignOut'
));

 /*
| Sign-in Page (GET)
*/
Route::get('/login',array(
    
    'as'=> 'login',
    'uses'=> 'AccessController@index'
));

/*
| Sign in (POST)
*/
Route::post('/logout',array(
'as'=> 'logout',
'uses' => 'AccessController@destroy'

));//post sign-in - end 

Route::get('/forgot',array(
	'as'	=> 'forgot', 
	'uses'	=> 'AccessController@forgotIndex'

	));
Route::post('/forgot', array(
	'as'	=> 'forgot',
	'uses'	=> 'AccessController@postForgot'

	));

Route::get('/recover/{code}', array(
		'as'  => 'account-recover',
		'uses'  => 'AccessController@getRecover'
	)
);

      Route::get('/change-password',array(
		  'before'=> 'auth',
		  'as'  => 'account-change-password',
		  'uses'  =>  'AccountController@getChangePassword'
	  ));

       /*
        | Change password (POST)
        */
        Route::post('/account/change-password',array(
			'before'=> 'auth',
			'as'  => 'account-change-password-post',
			'uses'  => 'AccountController@postChangePassword'
		));

Route::post('/new-password',array(
	'as'    =>'new-password',
	'uses'  => 'AccessController@postNewPassword'
));




