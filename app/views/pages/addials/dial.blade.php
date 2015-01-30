
<div class="content" xmlns="http://www.w3.org/1999/html">

<div class="alert alert-success">
    <p>This is the ad Dial portal. Once the call has completed you will receive your payment.{{----}} </p>
</div>





{{--<form action="#" method="post" id='callback' hidden>--}}
    {{--<span>Your Number:--}}
    {{--<input type="tel" id="mobile-number" value="" placeholder="valid telephone #" name="called"  /></span>--}}
    {{--<input type="hidden" value="{{$marketer->telephone}}" name="telephone">--}}

    {{--<input type="submit" class='button' id="connect" value="Connect"/>--}}

    {{--{{Form::token()}}--}}
{{--</form>--}}
    <div class="content">
        <p class="alert alert-danger">
            For the next 24 hours advertisements will be uploading slowly. Advertisements will increase in volume as they load into our servers.  Please be patient.
        </p>
    </div>
    <small>Click play to listen to the AdDial</small>
    <div class="ui360 ui360-vis">
        <a href="{{asset('assets/addials/')}}/{{$marketer->audio_file}}">Play</a>

    </div>


    <div class="form" hidden>

        <script type="text/javascript" src="https://secure.jotform.us/jsform/50266024595152"></script>
    </div>

    <div class="row">
        <small class="alert alert-warning"> please note: you are only paid once per Addials.</small>


    </div>



</div>

