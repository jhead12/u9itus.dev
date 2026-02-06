@extends('layout.pre')
@section('content')

<div class="platinum-signin" >

<div class="platinum-form">


    <div class="jumbotron">
        <h1>Platinum Membership</h1>
        <h3 style="color: white">$100 a Year.</h3>

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
    <!-- Credit card display form -->
    <form id="platinum"  action="{{URL::route('platinum-post')}}" method="POST" id="payment-form">
<div class="row">

</div>
<fieldset><legend>Credit Card Information</legend>
  <div class="small-12 large-6 ">
  <small style="color: red">You will be charge $100</small>
<label>

         Card Number:
         <input type="text" size="20" id="card" autocomplete="off" name="card" data-stripe="number" placeholder="1234 5678 9012 3456" />
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

    <div class="large-4 column">
        <label>

            <span>Exp Month</span>
           
            
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
    <div class="large-3 column">
 
   
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
    <label>Paypal Email: <small>How you will receive Payouts.Required(any email is fine)</small>
      <input type="text" id="paypal_email" name='paypal_email' {{ (Input::old('paypal_email')) ? 'value="' . e(Input::old('paypal_email')) . ' " ': '' }}>
    </label>
    @if($errors->has('paypal_email'))
    <small class="error">{{$errors-> first('paypal_email')}}</small>
    @endif
  </div>

<div class="large-4">
  
    {{Form::label('coupon', 'coupon code')
        }}
        {{Form::text('coupon');}}
 
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

  


<!-- paypal tab -->
<div class="tab-pane" id="paypal">

<div class="panel callout radius">
  <h5>Purchase a membership via Payal</h5>
  <p>Will be implemented soon.</p>
</div>
</div>

  </div>
  <!-- end of tab -->

 <div class="large-8"><p style="color: red;font-size:small">You are making a payment for a one year Platinum subscription to the Head Enterprises' Dial4dough.com/Adddials.com program.  You are paid $1.75 each time that you view, read, or listen to advertisements provided by our client advertisers.  Dial4dough.com cannot guarantee any specified amounts of advertisements that will be provided by Dial4dough for viewing. The amount of ads provided by the Dial4dough program all depends upon the amount of advertising that is received from our client advertisers.  Advertisers will provide advertisements to the Addials/Dial4dough system, and you the subscriber, will be provided advertisements to view, based upon geo-location and the type of advertisements requested by you the subscriber.

    In the event of a dispute, and subscribers wishes a refund, refunds will be made minus recruitment bonuses and prior payments made to subscriber.  If you have any questions regarding this payment please contact : Head Enterprises at 347-230-8438 Extension 1. You may also send us an email message to admin@dial4dough.com. </p>
</div>
</div>


</div>

     

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
        var form =  $( "#platinum" );


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



  <script>

    $(document).ready(function(){
        Stripe.setPublishableKey('pk_test_4RsC3yc5oVRJUzWPHkLJwNfH');
        var token       = $('input[name="_token"]').val();



        var submitInitialText   = $('.submit-button').text();

        function stripeResponseHandler(status, response) {
            if (response.error) {
                // re-enable the submit button
                var response = response.error;
                console.log(response['message']);

                $('.submit-button').removeAttr("disabled");
                $('.submit-button').text(submitInitialText);
                $.ajax({
    //             Will send to Post Create Platinum jsValidate
                    headers: {'X-CSRF-Token' : token},
                    url: '{{URL::to('plat')}}',
                    type: 'post',
                    data: response,
                    success:function(data){
                        $('#platinum').empty();
                        $('#platinum').html(data);




                    }
                });


                // show the errors on the form
                //$(".payment-errors").html(response.error.message);
               // $('.submit-button').text(submitInitialText);
            } else {
                $('.submit-button').removeAttr("disabled");


                alert('This is working fine');
                $('.submit-button').text(submitInitialText);


                $.ajax({
                    headers: {'X-CSRF-Token' : token},
                    url:'http://localhost/platinum',
                    data: response,
                    type:'post',
                    success:function(data){

                        console.log(data);
                    }
                });
                //var form$ = $("#payment-form");
                // token contains id, last4, and card type
                //var token = response['id'];
                // insert the token into the form so it gets submitted to the server
               // form$.append("<input type='hidden' name='stripeToken' value='" + token + "' />");
                // and submit
               // form$.get(0).submit();
            }
        }


            $('.submit-button').submit('click',function(event) {
                event.preventDefault();


                // disable the submit button to prevent repeated clicks
                $('.submit-button').attr("disabled", "disabled").text('Please wait...');

                $.ajax({
    //             Will send to Post Create Platinum jsValidate
                    headers: {'X-CSRF-Token' : token},
                    url: 'http://homestead.app:8000/platinum',
                    type: 'post',

                    success:function(data){
                        $('<html>').empty();
                        $('<html>').html(data);



                    }
                });


                // createToken returns immediately - the supplied callback submits the form if there are no errors


                return false; // submit from callback
            });


    });

    </script>
	<script type="text/javascript">
	
	
	var HowManyListItems = 8; // Specify number of "year" selections.
	var year = new Date().getFullYear();
	for(var i = 0; i < HowManyListItems; i++)
	{
	   var t = i + year;
	   $('').append('<option value="' + t + '">' + t + '</option>');
	}
	</script>

@stop

