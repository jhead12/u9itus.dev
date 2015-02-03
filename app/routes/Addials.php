<?php

App::bind('D4D\Repos\Pap\PapRepositoryInterface', 'D4D\Repos\Pap\DbPapRepository');
App::bind('D4D\Repos\Addials\AddialsRepositoryInterface','D4D\Repos\Addials\DbAddialsRepository');

Route::resource('addials','AddialsController');


/*
|Create addial- serial
*/
Route::post('/addial-serial',array(
    'before'=> 'auth',
    'as'  => 'addial-serial',
    'uses'    =>'SubscriptionController@createAddialSerial'
));



/*
| ** This is for checking the AccountController-- Post AddialCheck
*/
Route::post('/addial/confirm',array(
    'as'    => 'addial-confirm',
    'uses'  => 'AddialsController@confirm'
));

/*
|Test (Addial Pop-upfunction)
*/



Route::get('/addial/{id}',array(
    'as'    => 'addial-show',
    'uses'  => 'AddialsController@show'
));

Route::get('/complete', 'AddialsController@index');




Route::get('/forgot-pin', array(
    'before' => 'auth',
    'as'    => 'forgot-pin',
    'uses'  => 'AddialsController@forgotPin'
));

/*
| Callback --function for the Making a call --
*/

/*
| This is a function that can be used to create the addials
*/
// Route::post('/account/addial-create', array(
// 'as' => 'addial-create',
// 'uses' => 'MarketerController@postAddialCreate'
// ));

//Create account Post End