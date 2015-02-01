<?php


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

//Route::get('/addial/{id}',['as'=>'addials-show'],function($id){
//
//
//
//
//    $marketer = Marketer::where('id', '=', $id )->get();
//
//    Session::put('marketer',$marketer);
//
//
//    return View::make('pages.addials.business',compact('marketer'));
//
//
//});





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