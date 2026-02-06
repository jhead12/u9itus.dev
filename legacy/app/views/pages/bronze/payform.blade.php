@extends('layout.pre')


@section('content')
<div class="bronze-signin" xmlns="http://www.w3.org/1999/html">

    <form id="bronze"  class="bronze-form" action="{{URL::route('bronze-post')}}" method="POST" id="payment-form">
        <div class="jumbotron">
            <h1>Bronze Sign up Page</h1>

        </div>
         <ul class="pager">
              <li class="previous"><a href="{{URL::to('welcome')}}">&larr; back</a></li>

            </ul>

        <fieldset><legend>How you will be paid.</legend>
        {{--Pop up talking about the $600 w10 requirements--}}

          <div class="small-12 large-12">
                    <label>Payout Email: <small> This is the email you will receive payments with.</small>
                        <input id="paypal_email" type="text" name='paypal_email' {{ (Input::old('paypal_email')) ? 'value="' . e(Input::old('paypal_email')) . '"': '' }}>
                    </label>
                    @if($errors->has('paypal_email'))
                    <small class="error">{{$errors-> first('paypal_email')}}</small>
                    @endif
                </div>


        </fieldset>



        <fieldset><legend>Mailing Address</legend>

         <div class="small-12 large-12">
                    <label>Address: <small>required</small>
                        <input id="street_address" type="text" name='street_address' data-stripe="address_line1" {{ (Input::old('street_address')) ? 'value="' . e(Input::old('street_address')) . '"': '' }}>
                    </label>
                    @if($errors->has('street_address'))
                    <small class="error">{{$errors-> first('street_address')}}</small>
                    @endif
                </div>

                <div class="small-12 large-4 column">
                    <label>City: <small>required</small>
                        <input  type="text" id="city" name='city' data-stripe="address_city"{{ (Input::old('city')) ? 'value="' . e(Input::old('city')) . '"': '' }}>
                    </label>
                    @if($errors->has('city'))
                    <small class="error">{{$errors-> first('city')}}</small>
                    @endif
                </div>

                <div class="small-12 large-4 column">
                    <label>State: <small>required</small>
                        <input type="text" id="state" name='state' data-stripe="address_state"{{ (Input::old('state')) ? 'value="' . e(Input::old('state')) . '"': ''}}>
                    </label>
                    @if($errors->has('state'))
                    <small class="error">{{$errors-> first('state')}}</small>
                    @endif
                </div>

                <div class="small-12 large-4 column">
                    <label>Zip: <small>required</small>
                        <input type="text"  id="zip" name='zip'{{ (Input::old('zip')) ? 'value="' . e(Input::old('zip')) . '"': '' }}>
                    </label>
                    @if($errors->has('zip'))
                    <small class="error">{{$errors-> first('zip')}}</small>
                    @endif
                </div>

{{--Would like "country" on drop-down memu with USA, Canada, and territories of the United States to only be selected for all sign up forms.--}}

        </fieldset>







          <div class="large-6">

                                 <span>
                                     @if($errors->has('payagreement'))
                                     </span>
                                     <span class="around">
                                         @endif

                                 <input type="checkbox" id="checkbox" name="payagreement">
                                 </span><small>

                I agree to the <a href="{{URL::route('terms')}}"> Head Enterprises Payment agreement</a></small></span>
                <br/>
            <button type="submit" class="submit-button">Submit Entry</button>
          </div>
            {{Form::token()}}
    </form>

</div>

@stop

@section('scripts')

{{--<script src="{{asset('/js/soundmanager2.js')}}"></script>--}}

<script>
    $(document).ready(function(){
        //var token = $('input[name="_hidden"]');
        var form =  $( "#bronze" );


       form.click(function(event){
           //event.preventDefault();
           form.validate({

               rules: {
                    email: "required",

                    paypal_email: "required",
                    state:  "required",
                    city:    "required",
                    street_address: "required",
                    zip:    "required",
                    checkbox:"required"

               },messages:{

                                email: "Please enter a valid email address",
                                password: "Please enter a valid password",
                                paypal_email: "Please enter a payout email",
                                city:    "Please enter a vaild city",
                                state:  "Please enter a valid state",
                                street_address: "Please enter your mailing address",
                                zip:    "Please enter your proper zip code"
                                }






           });




       });




    });
    </script>



@stop
