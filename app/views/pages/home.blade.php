@extends('layouts.default')

@section('content')


  <main class="bs-docs-masthead" id="fixed-top" role="main">
    <div class="header-unit">


        <div class="hero">
          <span class="bs-docs-booticon bs-docs-booticon-lg "><img src="{{asset('images/off.images/logo.png')}}" alt="logo"/></span>
          <p class="lead">Get paid $1.75 reviewing AdDials</p>
          <p class="lead">
            <a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="btn btn-primary">Sign Up</a>
          </p>

        </div>
      <div id="video-container" >
        <video autoplay loop  poster="{{asset('images/bg0809.jpg')}}" class="fillWidth">

          <source src="{{asset('video/hero_slide.mp4')}}" type="video/mp4"/>
        </video>





      </div>


    </div>
  </main>

{{--<div id="top" class="header large-10">--}}
    {{--<div class="vert-text ">--}}

      {{--<!-- Main component for a primary marketing message or call to action -->--}}
      {{--<div class="jumbotron">--}}
        {{--<h1>Get paid up to $1.75 reviewing advertisements, or what we call AdDials</h1>--}}
      {{----}}
        {{--<p>Advertisers look for loyalty, Dial4dough pays you for you loyalty.</p>--}}
        {{--<p>--}}
          {{--<a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="'btn btn-lg btn-primary ">Sign Up</a>--}}
        {{--</p>--}}
      {{--</div>--}}

      @include('pages.partials.content')

      @include('layouts.partials.footer')

    {{--</div> <!-- /container -->--}}
{{--</div>--}}
@stop
