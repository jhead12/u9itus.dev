
<div id="panel">

<h1>Congratulations!</h1>


<p>You are now a Bronze Member in the Dial4dough program.</p>

<p>This is your pin <strong>{{Auth::user()->pin}}</strong></p>
<p>You will use this pin to access each Addial</p>

<p>Click on the <a href="{{URL::route('dialpad')}}">Dialpad</a> to get started.</p>



<p>Below are the benefits that you receive for being a Bronze Member:</p>
<ul>

<li>$.25 for each addial reviewed;</li>
<li>$15.00 for each Platinum member sponsored;</li>
</ul>

<p>
** Remember that Bronze members may upgrade their membership at any time.<br>
** All Dial4dough members that review addials, are obligated to make purchases from advertisers in the Dial4dough program.  Once a Dial4dough member earns at least $100 through the Dial4dough system, a minimum 20 % ($20) purchase is required to continue receiving Addials.
Please click on the "Dash Board" link above to review Addials. <br>

<a href="{{URL::route('upgrade')}}">You can Upgrade</a>

<p><a href="{{URL::route('addial-info')}}"> Click link </a>for Instructions</p>
Thank You,
<br/>
<strong> Admin Dial4dough.com</strong>

<br/>


<br/>
<div class="large-6">
<a id="off" href="{{URL::route('select-info-off')}}">I get it, please do not show this message again.</a>

</div>

</p>

<a  class="close-reveal-modal">&#215;</a>

</div>

@section('scripts')
<script>
//$('#off').on('click',function(event){
//    event.preventDefault();
//
//
//    $.ajax({
//
//        url: "http://localhost:8000/select-info-off",
//        type: 'GET',
//        success:function(data){
//            alert(data);
//
//
//        }
//    });
//
//
//});
</script>


@stop