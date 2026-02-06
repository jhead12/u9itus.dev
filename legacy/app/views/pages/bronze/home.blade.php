@extends('layout.main')


@section('content')
@include('account.bronze.layout.sidemenu')
@include('account.addials.adpad')
 <li><p>Call us: <a style="color: red" href="tel:3472308438">3472308438 ext#1</a></p></li>
<div class="helper-bottom-right">
<a class="helpers feedback"href="{{URL::route('forum')}}"></a>
</div>

@if(Auth::user()->campaignid ==="88f76db3" && Auth::user()->info ===1)
<a href="#" data-reveal-id="bronze"></a>

<div id="bronze" class="reveal-modal" data-reveal>
 @include('account.bronze.success')
</div>
@endif


@stop

