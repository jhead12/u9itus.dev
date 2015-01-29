<div class="panel" xmlns="http://www.w3.org/1999/html">

<div class="alert alert-success">
    <p>This is the ad Dial portal. Once the call has completed you will receive your payment.{{----}} </p>
</div>

<div class="row">



{{--<form action="#" method="post" id='callback' hidden>--}}
    {{--<span>Your Number:--}}
    {{--<input type="tel" id="mobile-number" value="" placeholder="valid telephone #" name="called"  /></span>--}}
    {{--<input type="hidden" value="{{$marketer->telephone}}" name="telephone">--}}

    {{--<input type="submit" class='button' id="connect" value="Connect"/>--}}

    {{--{{Form::token()}}--}}
{{--</form>--}}
    <small>Click play to listen to the AdDial</small>
    <div class="ui360 ui360-vis">
        <a href="{{asset('assets/addials/')}}/{{$marketer->audio_file}}"></a>
    </div>


    <div class="form" hidden>

        <script type="text/javascript" src="https://secure.jotform.us/jsform/50266024595152"></script>
    </div>


</div>

</div>

