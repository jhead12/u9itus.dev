<ul class="nav nav-tabs" role="tablist" id="myTab">

    <!-- If statement that will indicate if the if the dialpad is a video/live/audio dial -->
    <li class="active"><a href="#action" role="tab" data-toggle="tab">Dial Connect</a></li>
    <li><a href="#business" role="tab" data-toggle="tab">Business Info/product Id</a></li>



</ul>

<div class="tab-content">
    <div class="tab-pane active" id="action">
       

@if($marketer->type==='video')
<h1>Video Review Addial</h1>
<p>Please review the complete video to receive commission. </p>
   <div class="flex-video">
        <iframe width="420" height="315" src="{{$marketer->video_url}}" frameborder="0" allowfullscreen></iframe>
</div>
     @include('pages.addials.video')
@else
@include('pages.addials.dial')
 
@endif


    </div>
    <div class="tab-pane" id="business">

 @include('pages.addials.info')

    </div>

        </div>


<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>