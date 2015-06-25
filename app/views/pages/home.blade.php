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


      <p style="font-size: larger">Earn as much as $1.75 to Listen to  2 minute infomercials!</p>
      <ul class="actions ">
          <li><a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="button special">Sign Up</a></li>
          <li><a href="{{URL::to('about')}}" class="button">Learn More</a></li>
      </ul>
  </section>


      @include('pages.partials.content')

      @include('layouts.partials.footer')

    {{--</div> <!-- /container -->--}}
{{--</div>--}}
@stop
