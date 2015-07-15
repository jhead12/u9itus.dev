<?php

class PageController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function home()
	{

		return View::make('pages.home');
	}
	public function about()
	{
		return View::make('pages.aboutus');
	}
	public function headent()
	{
		return View::make('pages.headent');
	}

    /**
     * @return mixed
     */
    public function redirect()
    {
        if( Input::get('data')){
            return Redirect::to('/')->with('info','Please Login to the Affiliates panel to access the Dialpad ');
        }
    }
	public function pricing()
	{
		return View::make('pages.pricing');
	}
	public function thankyou()
	{
		return View::make('pages.thankyou');
	}
	public function privacy()
	{
		return View::make('rules.privatepolicie');
	}
	public function terms()
	{
		return View::make('rules.terms');
	}

}
