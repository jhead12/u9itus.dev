@extends('layout.main')


@section('content')
@include('account.bronze.layout.sidemenu')
@include('account.addials.adpad')

 <li><p>Call us: <a style="color: red" href="tel:3472308438">3472308438 ext#1</a></p></li>
@if(Auth::user()->info == 1)
<a href="#" data-reveal-id="gold"></a>

<div id="gold" class="reveal-modal" data-reveal>
 @include('account.gold.success')
</div>
@endif


@stop

@section('scripts')



<script>

$(document).ready(function(){
$('a[data-reveal-id="gold"]').trigger('click');


});




</script>

@stop
