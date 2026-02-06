@extends ('layouts.default')

@section('content')
<div id="top" class="signin">
    <div class="form-bg">
<h2>Forgot Password.</h2>
        <p>Please Enter your email to recover account.</p>

<form id='forgot'action="{{URL::route('forgot')}}" method='post'>
	<div class="form-group">
  		<label class="control-label" >Email
  		<input type="text" class="form-control" name="email"id="inputSuccess2" {{ (Input::old('email')) ? 'value="' . e(Input::old('email')) . '"' : ''}}>
        </label>

@if($errors->has('email'))

<div data-alert class="alert-box alert ">
  {{$errors->first('email')}}
  <a href="#" class="close">&times;</a>
</div>

@endif
 
</div>

	<input class="btn btn-lg btn-primary btn-block" type="submit" value="Recover">
	{{Form::token()}}
</form>
        </div>
</div>

@stop

@section('scripts')


<script>
    $(document).ready(function(){
        //var token = $('input[name="_hidden"]');
        var form =  $( "#forgot" );


       form.click(function(event){
           //event.preventDefault();
           form.validate({

               rules: {
                    email: "required"


               },messages:{

                  email: "Please enter a valid email address"

           }

       })

    });
    });
    </script>

@stop