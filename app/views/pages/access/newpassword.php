@extends ('layouts.default')

@section('content')

<div class="signin" >


<div class="form-bg">
<form class="form-horizontal" action="{{ URL::route('new-password')}}" method="post">



  <div class="form-group">
    	<label for="inputPassword"  class="col-sm-2 control-label">New Password</label>
    	<div class="col-sm-10">
      <input type="password" class="form-control" name='password' id="inputPassword" placeholder="New Password">
    @if ($errors->has('password'))
{{ $errors ->first('password') }}
	  	  @endif
    </div>
</div>

    <div class="form-group">
    <label for="inputPassword"  class="col-sm-2 control-label">Confirm Password</label>
    <div class="col-sm-10">
      <input type="password" class="form-control" name='password_again' id="inputPassword" value="Change Password" placeholder="Password" autocomplete="off">
    @if ($errors->has('password_again'))
{{ $errors ->first('password_again') }}
  	  @endif

    </div>
    <input type="hidden" name="userid" value="{{$user['userid']}}"/>

  </div>


  <input class='button' type='submit' value="Change Password">
  {{ Form::token()}}
</form>
</div>
</div>

@stop