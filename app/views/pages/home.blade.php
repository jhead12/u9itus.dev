@extends('layouts.default')

@section('content')

<div class="container">

      <!-- Main component for a primary marketing message or call to action -->
      <div class="jumbotron">
        <h1>The Economic Explosion</h1>
        <p>Get paid up to $1.75 reviewing advertisements, or what we call AdDials.</p>
        <p>Advertisers look for loyalty, Dial4dough pays you for you loyalty.</p>
        <p>
          {{ link_to_route('register_path', 'Sign Up!', null, ['class'=>'btn btn-lg btn-primary'])}}
          
        </p>
      </div>

      @include('pages.partials.content')
      @include('pages.partials.footer')

    </div> <!-- /container -->

@stop
