<ul class="nav nav-tabs" role="tablist" id="myTab">

    <!-- If statement that will indicate if the if the dialpad is a video/live/audio dial -->
    <li class="active"><a href="#action" role="tab" data-toggle="tab">Dial Connect</a></li>
    <li><a href="#business" role="tab" data-toggle="tab">Business Info</a></li>
    <li><a href="#map" role="tab" data-toggle="tab">Map</a></li>


</ul>

<div class="tab-content">
    <div class="tab-pane active" id="action">
       

@if($marketer->type==='video')
<h1>Video Review Addial</h1>
<p>Please review the complete video to receive commision. </p>
   <div class="flex-video">
        <iframe width="420" height="315" src="{{$marketer->video_url}}" frameborder="0" allowfullscreen></iframe>
</div>
     @include('account.addials.video')   
@else
@include('account.addials.dial')
 
@endif


    </div>
    <div class="tab-pane" id="business">

 @include('account.addials.info')

    </div>
    <div class="tab-pane" id="map">



     @include('account.addials.map')

        </div>
        </div>


<a class="close-reveal-modal">&#215;</a>