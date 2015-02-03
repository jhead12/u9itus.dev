
<div class="content" xmlns="http://www.w3.org/1999/html">

<div class="alert alert-success" hidden>
    <p>This is the ad Dial portal. Once the call has completed you will receive your payment.{{----}} </p>
</div>


@foreach($marketer as $data)



    <div class="content">
        <p class="alert alert-danger">
            For the next 24 hours advertisements will be uploading slowly. Advertisements will increase in volume as they load into our servers.  Please be patient.
        </p>
    </div>
        @if($data->type === "audio")
    <small>Click play to listen to the AdDial</small>
    <div class="ui360 ui360-vis">
        <a href="{{asset('assets/addials/')}}/{{$data->audio_file}}"></a>
    </div>

<div id="productID"></div>

        @elseif($data->type === "telephone")


            @include('pages/addials/telephone')

        @endif

    @endforeach

    <div class="form" hidden>

        <script type="text/javascript" src="https://secure.jotform.us/jsform/50266024595152"></script>



    <div class="content" id="message">

        <small class="alert alert-warning"> please note: you are only paid once per Addials.</small>


    </div>

</div>

</div>



@include('layouts.partials.footer')
