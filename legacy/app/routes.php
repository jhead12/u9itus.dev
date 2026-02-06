<?php


/*CSRF*/
//Route::when('*','csrf',['post','put','patch']);


/** ------------------------------------------
 *  Route model binding
 *  ------------------------------------------
 */
Route::model('user', 'User');
Route::model('comment', 'Comment');
Route::model('post', 'Post');
Route::model('addial','Addials');
Route::model('marketer', 'Marketer');
Route::model('role', 'Role');

/** ------------------------------------------
 *  Route constraint patterns
 *  ------------------------------------------
 */
Route::pattern('comment', '[0-9]+');
Route::pattern('post', '[0-9]+');
Route::pattern('user', '[0-9]+');
Route::pattern('role', '[0-9]+');
Route::pattern('token', '[0-9a-z]+');







 
/*
|--------------------------------------------------------------------------
| Application 404 & 500 Error Handlers
|--------------------------------------------------------------------------
|
| To centralize and simplify 404 handling, Laravel uses an awesome event
| system to retrieve the response. Feel free to modify this function to
| your tastes and the needs of your application.
|
| Similarly, we use an event to handle the display of 500 level errors
| within the application. These errors are fired when there is an
| uncaught exception thrown in the application.
|
*/
Event::listen('404', function()
{
   Response::error('404');
   return Redirect::back();
});

Event::listen('403', function()
{
  Response::error('403');
 
});
Event::listen('500', function()
{
  return Response::error('500');
});

foreach(File::allfiles(__DIR__.'/routes') as $partial)
{

  //var_dump($partial->getPathname());
  require_once $partial->getPathname();
}
//

