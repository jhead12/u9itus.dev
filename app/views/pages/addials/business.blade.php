@extends('layouts.default')


@section('content')

    <div class="container">




    <ul class="pager">
    <li type="btn " class="previous"><a href="{{URL::to('dialpad')}}">&larr; back</a></li>
    </ul>
<ul class="nav nav-tabs" role="tablist" id="myTab">

    <!-- If statement that will indicate if the if the dialpad is a video/live/audio dial -->
    <li class="active"><a href="#action" role="tab" data-toggle="tab">Dial Connect</a></li>
    <li><a href="#business" role="tab" data-toggle="tab">Business Info/product Id</a></li>




</ul>

<div class="tab-content">
    <div class="tab-pane active" id="action">



@include('pages.addials.dial',array($marketer))




    </div>
    <div class="tab-pane" id="business">

 @include('pages.addials.info')

    </div>

</div>

<form action="hidden" id="name" data-name="{{$marketer}}"></form>

<script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
<script type="text/javascript">

    var name = $('#name').data('marketer');


</script>


    </div>

