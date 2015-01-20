@extends('layouts.down')

@section('content')



        
      <!-- Main component for a primary marketing message or call to action -->
      <div class="vert-text ">
        <div >
            
            <div class="alert alert-danger" style="padding-top:70px;"> We are repairing and updating the AdDials application. We will be back soon. Feel free to contact us if you have any questions or contributions. Thank you.  </div>
        <img src="{{asset('images/off.images/logo.png')}}">
   
        <h2>Get paid up to $1.75 reviewing advertisements, or what we call AdDials</h2>
      
        <p>Advertisers look for loyalty, Dial4dough pays you for you loyalty.</p> 
        </div>
       
       
      </div>

      @include('pages.partials.content')
      @include('pages.partials.footer')

@stop
