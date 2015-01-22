@extends('layouts.default')

@section('content')
<div id="top" class="signin">

    <div class="form-bg">

<form method="POST" action="{{{ URL::to('users') }}}" accept-charset="UTF-8">

<div class="jumbotron"><h1>Sign Up</h1> <p>If you have any questions or concerns feel free to contact us: 347-230-8438</p></div>
        <div class="large-6"><small>* Fields required</small></div>
        <br/>

        @if(Session::get('error') )

            <div class="alert alert-danger">
            @foreach(Session::get('error') as $error)
                <li>{{$error}}</li>
            @endforeach
                
            </div>
        @endif

    <input type="hidden" name="_token" value="{{{ Session::getToken() }}}">
    <fieldset>
        <div class="form-group">
            <label for="username">{{{ Lang::get('confide::confide.username') }}}</label>
            <input class="form-control" placeholder="{{{ Lang::get('confide::confide.username') }}}" type="text" name="username" id="username" value="{{{ Input::old('username') }}}">
        </div>
        <div class="form-group">
            <label for="email">{{{ Lang::get('confide::confide.e_mail') }}} <small>{{ Lang::get('confide::confide.signup.confirmation_required') }}</small></label>
            <input class="form-control" placeholder="{{{ Lang::get('confide::confide.e_mail') }}}" type="text" name="email" id="email" value="{{{ Input::old('email') }}}">
        </div>
        <div class="form-group">
            <label for="email">{{{ Lang::get('confide::confide.telephone') }}} <small>{{ Lang::get('confide::confide.signup.confirmation_required') }}</small></label>
            <input class="form-control" placeholder="{{{ Lang::get('confide::confide.telephone') }}}" type="tel" name="telephone" id="telephone" value="{{{ Input::old('telephone') }}}">
        </div>
        <div class="form-group">

            <label>Gender </label>

            <input type="radio" name="sex" value="male" id="gender_set"><label for="gender_set">Male</label>
            <input type="radio" name="sex" value="female" id="gender_set"><label for="gender_set">Female</label>

            @if($errors->has('sex'))
            <small class="error">{{$errors->first('sex')}}</small>
            @endif

        </div>

        <div class="form-group">

                {{Form::label('country', ' Available Countries', array('class' => 'large-5','columns','left'))}}
                {{Form::select('country', array(
                'Available countries' => array("USA","United Kingdom","Virgin Islands","Canada","Guam","Domican Republic","Puerto Rico")

                ));}}


            </div>
        <div class="form-group">
            <label for="password">{{{ Lang::get('confide::confide.password') }}}</label>
            <input class="form-control" placeholder="{{{ Lang::get('confide::confide.password') }}}" type="password" name="password" id="password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">{{{ Lang::get('confide::confide.password_confirmation') }}}</label>
            <input class="form-control" placeholder="{{{ Lang::get('confide::confide.password_confirmation') }}}" type="password" name="password_confirmation" id="password_confirmation">
        </div>




        @if (Session::get('notice'))
            <div class="alert">{{ Session::get('notice') }}</div>
        @endif

        <div class="form-actions form-group">
          <button type="submit" class="btn btn-primary">{{{ Lang::get('confide::confide.signup.submit') }}}</button>
        </div>

    </fieldset>
</form>
</div>
</div>


@include('pages.partials.footer')
@include('pages.scripts.create')

