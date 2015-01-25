@extends('layouts.default')

@section('content')

<div id="top" class="header large-10">
    <div class="vert-text ">

      <!-- Main component for a primary marketing message or call to action -->
      <div class="jumbotron">
        <h1>Get paid up to $1.75 reviewing advertisements, or what we call AdDials</h1>
      
        <p>Advertisers look for loyalty, Dial4dough pays you for you loyalty.</p>
        <p>
          <a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="'btn btn-lg btn-primary ">Sign Up</a>
        </p>
      </div>

      @include('pages.partials.content')
      @include('pages.partials.footer')

    </div> <!-- /container -->
</div>
@stop
