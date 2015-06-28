@extends('layouts.default')

@section('content')


  {{--<main class="bs-docs-masthead" id="content" role="main">--}}
    {{--<div class="container">--}}
      {{--<span class="bs-docs-booticon bs-docs-booticon-lg "><img src="{{asset('images/off.images/logo.png')}}" alt="logo"/></span>--}}
      {{--<p class="lead" style="color:darkblue;">Get paid $1.75 reviewing AdDials</p>--}}
      {{--<p class="lead">--}}
        {{--<a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="btn btn-primary">Sign Up</a>--}}
      {{--</p>--}}

    {{--</div>--}}
  {{--</main>--}}

  <!-- Banner -->
  <section id="banner" data-layer="true" data-speed="4" data-background="true">
        <p>     <img src="{{asset('images/off.images/logo.png')}}" alt="logo"/>
        </p>


      <h1 style="font-size: xx-large;color: #ffff00">Earn as much as $1.75 to Listen to  2 minute infomercials!</h1>
      <ul class="actions ">
          <li><a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="button special ">Sign Up Now!</a></li>
          <li><a href="{{URL::to('about')}}" class="button">Learn More</a></li>
      </ul>
  </section>


      @include('pages.partials.content')



    {{--</div> <!-- /container -->--}}
{{--</div>--}}
@stop
