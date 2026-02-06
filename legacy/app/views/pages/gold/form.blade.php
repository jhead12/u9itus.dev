@extends('layout.pre')


@section('content')

<div class="gold-signin" >


    <div class="jumbotron">
        <h1>Gold Membership</h1>

    </div>
    <ul class="pager">
      <li class="previous"><a href="{{URL::to('welcome')}}">&larr; back</a></li>

    </ul>

    <ul class="nav nav-tabs" role="tablist" id="myTab">
  <li class="active"><a href="#cc" role="tab" data-toggle="tab">Pay with Credit Card</a></li>

    <li><a href="#paypal" role="tab" data-toggle="tab">pay with paypal</a></li>


</ul>

<div class="tab-content">

  <div class="tab-pane active" id="cc"> 
<div class="row">
  <form id="gold"  class="gold-form" action="{{URL::route('gold-post')}}" method="POST" id="payment-form">
  <p>Gold Membership: $10 Monthly.</p>
  {{--Create Hover button that shows the details of the Membership--}}
</div>
<fieldset><legend>Credit Card Information</legend>
  <div class="small-12 large-6 ">
<label>

         Card Number:
         <input type="text" size="20" id="card" autocomplete="off" name="card" data-stripe="number" />
     </label>
        @if($errors->has('card'))
    <small class="error">{{$errors-> first('card')}}</small>
    @endif
    </div>

    <div class="large-2 columns ">

        <label>
           CVC
            <input type="text" id="cvc" autocomplete="off" size="4" name="cvc"data-stripe="cvc"/>
        </label>
         @if($errors->has('cvc'))
    <small class="error">{{$errors-> first('cvc')}}</small>
    @endif
    </div>

  <div class="large-4 columns">
      <label>

          <span>Exp month</span>
          
		<select name="exp-month" id="exp-month" data-stripe="exp-month"  onchange="" size="1" >
		    <option value="01">January</option>
		    <option value="02">February</option>
		    <option value="03">March</option>
		    <option value="04">April</option>
		    <option value="05">May</option>
		    <option value="06">June</option>
		    <option value="07">July</option>
		    <option value="08">August</option>
		    <option value="09">September</option>
		    <option value="10">October</option>
		    <option value="11">November</option>
		    <option value="12">December</option>
		</select>
		
		
		
      </label>
       @if($errors->has('exp-month'))
  <small class="error">{{$errors-> first('exp-month')}}</small>
  @endif
  </div>
	
	
	
	
	
	
	
    <div class="large-3 columns">
 
   
	
	<label for="exp-year">Expiration Year:</label>
	<select id="exp-year" name="exp-year">
	  <option value="" selected>Please select a year</option>
	</select>
         @if($errors->has('exp-year'))
    <small class="error">{{$errors-> first('exp-year')}}</small>
    @endif
	 </div>
	


    <div class="row ">
    <div class="large-6">
        <span>Name: <small>As it appears on Card.</small></span>
            <input type="text" id="name" name="name"data-stripe="name" placeholder="Full name" {{ (Input::old('name')) ? 'value="' . e(Input::old('name')) . '"': '' }}/>

        @if($errors->has('name'))
        <small class="error">{{$errors-> first('name')}}</small>
        @endif
        </div>
    </div>

</fieldset>

<fieldset><legend>Mailing Address</legend>



  <div class="small-12 large-6 ">
    <label>Address: <small>required</small>
      <input type="text" name='street_address' id="address" data-stripe="address_line1" {{ (Input::old('street_address')) ? 'value="' . e(Input::old('street_address')) . ' " ': '' }}>
    </label>
    @if($errors->has('street_address'))
    <small class="error">{{$errors-> first('street_address')}}</small>
    @endif
  </div>
  
  <div class="small-12 large-4 column">
    <label>City: <small>required</small>
      <input type="text" id="city" name='city' data-stripe="address_city"{{ (Input::old('city')) ? 'value="' . e(Input::old('city')) . ' " ': '' }}>
    </label>
    @if($errors->has('city'))
    <small class="error">{{$errors-> first('city')}}</small>
    @endif
  </div>
  
  <div class="small-12 large-4 column">
    <label>State: <small>required</small>
      <input type="text" id="state" name='state' data-stripe="address_state"{{ (Input::old('state')) ? 'value="' . e(Input::old('state')) . ' " ': ''}}>
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

  </fieldset>
  
  

  <div class="small-12 large-4">
    <label>Paypal Email: <small>How you will receive Payouts.</small>
      <input type="text" id="paypal_email" name='paypal_email' {{ (Input::old('paypal_email')) ? 'value="' . e(Input::old('paypal_email')) . ' " ': '' }}>
    </label>
    @if($errors->has('paypal_email'))
    <small class="error">{{$errors-> first('paypal_email')}}</small>
    @endif
  </div>



        <small class="large-12 columns">

                                 <span>
                                     @if($errors->has('payagreement'))
                                     </span>
                                     <span class="around">
                                         @endif

                                 <input  id="checkbox" type="checkbox" name="payagreement">
                                 </span>

            I agree to the <a href="{{URL::route('terms')}}"> Head Enterprises Payment agreement</a></small>
           <div class="large-6">
            <button type="submit" class="submit-button">Submit Payment</button>
           </div>

        {{Form::token()}}
</form>

</div>
<div class="tab-pane" id="paypal">

<div class="panel callout radius">
  <h5>Purchase a membership via Payal</h5>
  <p>Will be implemented soon.</p>
</div>
</div>


<p style="font-size: small;color: red">You are making a payment for a one month Gold subscription to the Head Enterprises' Dial4dough.com/Addials.com program.  You are paid $1.00 each time that you view, read, or listen to advertisements provided by our client advertisers.  Dial4dough.com cannot guarantee any specified amounts of advertisements that will be provided by Dial4dough for viewing. The amount of ads provided by the Dial4dough program all depends upon the amount of advertising that is received from our client advertisers.  Advertisers will provide advertisements to the Addials/Dial4dough system, and you the subscriber, will be provided advertisements to view, based upon geo-location and the type of advertisements requested by you the subscriber.

                                        In the event of a dispute, and subscribers wishes a refund, refunds will be made minus recruitment bonuses and prior payments made to subscriber.  If you have any questions regarding this payment please contact : Head Enterprises at 347-230-8438 Extension 1. You may also send us an email message to admin@dial4dough.com. </p>
</div>
<!-- Tab ending. -->

@stop



@section('scripts')
 <script>

  $('#card').validateCreditCard(function(results){
        
          $('#card').append(results.card_type);
         // ('CC type' + results.card_type
         //   + '\nLength validation:' + results.length_valid 
         //    + '\nLuhn validation: ' + results.luhn_valid );

  });


  </script>

<script>
	
var date = new Date().getFullYear();
var length = date + 16;

for(var i = date; i < length; i++){
  // With jQuery:
  $('#exp-year').append('<option value="' + i + '">' + i + '</option>');
  // Or, we could do it with vanilla JavaScript like so:
  // document.getElementById('expiration-year').insertAdjacentHTML('beforeend', '<option value="' + i + '">' + i + '</option>');
}
	
</script>

<script>


    $(document).ready(function(){
        var token = $('input[name="_hidden"]');
        var form =  $( "#gold" );


       form.click(function(event){
           //event.preventDefault();
           form.validate({

               rules: {

                    card:    "required",
                     cvc: "required",
                     month: "required",
                    year: "required",
                    name:"required",
                    city:    "required",
                    zip:"required",
                   state: "required",
                   checkbox:"required",
                   street_address: "required",
                   paypal_email: "required"

               },
               messages:{
                   firstName: "Please enter your first name",
                   lastName: "Please enter your last name",
                   username: "A username is required",
                   telephone:"Please enter a valid Telephone number",
                   email: "Please enter a valid email address",
                   city:    "Please enter a valid city",
                   state:   "Please enter a valid state"



               }
           });




       });


    });
    </script>
 
 


	
	

@stop

