<?php

/*
|--------------------------------------------------------------------------
| Application & Route Filters
|--------------------------------------------------------------------------
|
| Below you will find the "before" and "after" events for the application
| which may be used to do any work before or after a request into your
| application. Here you may also register your custom route filters.
|
*/

App::before(function($request)
{
	//
});


App::after(function($request, $response)
{
	//
});

 App::before(function($request)
 {
     if( ! Request::secure())
     {
         return Redirect::secure(Request::path());
     }
 });

/*
|--------------------------------------------------------------------------
| Pap Login Filters
|--------------------------------------------------------------------------
|
|The following filters will check if the user is signed into Pap loggin
| If they are not will redirect them to the pap Login
|
|
*/
Route::filter('logged',function(){
	//Get cross site cookie from Pap
	//Check if the cookie has the logged in User
	//If the user is logged in allow them access to the page.
	//If the user is not redirect them to the log in page

});

/*
|--------------------------------------------------------------------------
| Authentication Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that the user of the current
| session is logged into this application. The "basic" filter easily
| integrates HTTP Basic authentication for quick, simple checking.
|
*/

Route::filter('auth', function()
{
	if (Auth::guest())
	{
		if (Request::ajax())
		{
			return Response::make('Unauthorized', 401);
		}
		else
		{
			return Redirect::guest('login');
		}
	}
});


Route::filter('auth.basic', function()
{
	return Auth::basic();
});

/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
|
| The "guest" filter is the counterpart of the authentication filters as
| it simply checks that the current user is not logged in. A redirect
| response will be issued if they are, which you may freely change.
|
*/

Route::filter('guest', function()
{
	if (Auth::check()) return Redirect::to('/');
});

/*
|--------------------------------------------------------------------------
| CSRF Protection Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/

Route::filter('csrf', function($route, $request)
{
	if (strtoupper($request->getMethod()) === 'GET')
	{
		return; // get requests are not CSRF protected
	}

	$token = $request->ajax() ? $request->header('X-CSRF-Token') : Input::get('_token');

	if (Session::token() != $token)
	{
		throw new Illuminate\Session\TokenMismatchException;
	}
});
//Pap Filter
Route::filter('pap', function(){

	//if the request cookies is loggin then allow the user to use the dialpad


	if( ! isset($_COOKIE['PAPVisitorId']) )
	{
		//return Redirect::to('/')->with('global-warning', 'Please log in order to access this page.');

	}

});




/*
|--------------------------------------------------------------------------
| Dialpad action Filter
|--------------------------------------------------------------------------
|
| 
|
*/

Route::filter('dialpad', function(){

	// $addials = Addial::where('completed','=', true)->distinct('userclickId')->get();
	$marketer = Marketer::where('amount','=', 0)->get();
	//return count($marketer);
	if( count($marketer) > 0){

			
			$id = Marketer::distinct('_id')->get();
			
			foreach ($id as $key => $value) {
				# code...
				return $value->$id;
			}

			//Marketer::Destroy($id);
			

	}else{

		
	}
	

 

    }
);

/*
|--------------------------------------------------------------------------
| Role Permissions
|--------------------------------------------------------------------------
|
| Access filters based on roles.
|
*/

// Check for role on all admin routes, minimum admin level
Entrust::routeNeedsRole( 'admin*', array('admin'), Redirect::to('/') );

// Check for permissions on admin actions
Entrust::routeNeedsPermission( 'admin/blogs*', 'manage_blogs', Redirect::to('/admin') );
Entrust::routeNeedsPermission( 'admin/comments*', 'manage_comments', Redirect::to('/admin') );
Entrust::routeNeedsPermission( 'admin/users*', 'manage_users', Redirect::to('/admin') );
Entrust::routeNeedsPermission( 'admin/roles*', 'manage_roles', Redirect::to('/admin') );



//This filter check if the user has already completed the addial.
Route::filter('user', 'DialpadFilter');



/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
|
| Detect the browser language.
|
*/

Route::filter('detectLang',  function($route, $request, $lang = 'auto')
{

	if($lang != "auto" && in_array($lang , Config::get('app.available_language')))
	{
		Config::set('app.locale', $lang);
	}else{
		$browser_lang = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtok(strip_tags($_SERVER['HTTP_ACCEPT_LANGUAGE']), ',') : '';
		$browser_lang = substr($browser_lang, 0,2);
		$userLang = (in_array($browser_lang, Config::get('app.available_language'))) ? $browser_lang : Config::get('app.locale');
		Config::set('app.locale', $userLang);
		App::setLocale($userLang);
	}
});
