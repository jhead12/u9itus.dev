@extends('layout.adpad')


@section('content')

<div class="jumbotron"><p>This is the area where members can make purchases for products that they have viewed.
<span class="right"><span style="background: blue;height: 30px;"></span> </span>
<div class="helper-bottom-right  " >


<a class="helpers feedback hint--left" data-hint="If you have an idea, inspiration or you just want to talk continue here." href="https://forums.dial4dough.com"></a>

</div>
Remember ** Members must make minimum purchases of 20 % of the addials viewing earnings received from Dial4dough to enable receiving Addials.</p></div>
@foreach ($marketers as $marketer)
   <div class="panel-group" id="{{$marketer->_id}}">
     <div class="panel panel-default">
       <div class="panel-heading">
         <h4 class="panel-title">
           <a data-toggle="collapse" data-parent="{{$marketer->_id}}" href="#collapse{{$marketer->_id}}">
             {{$marketer->title}}
           </a>
         </h4>
       </div>
       <div id="collapse{{$marketer->_id}}" class="panel-collapse collapse in">
         <div class="panel-body">
            <h2>{{$marketer->title}}</h2>
           <p>The marketer -- {{$marketer->user}} </p>
           {{$marketer->content}}
           <div class="large-5">

            <a href="{{$marketer->video_url}}">{{$marketer->video_url}}</a>

           </div>

         </div>
       </div>
     </div>
     </div>


@endforeach


<!-- Create a javascript that will indicate the addials that the user has used. -->
@stop