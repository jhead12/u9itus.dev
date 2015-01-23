<!DocType html>

  <head>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      
    <!-- Latest compiled and minified CSS -->

        <script src="https://js.stripe.com/v2"></script>


        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
        <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">
         <link href="//code.ionicframework.com/ionicons/1.5.2/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="{{asset('css/foundation.min.css')}}">
     <link href="{{asset('css/layout.css')}}" rel="stylesheet" type="text/css" />
    
      
        <!-- Optional theme -->
<!--<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">-->


  </head>
  <body>

  @include('layouts.partials.downNav')

 @if(Session::has('global-confirmation'))

<div id="global-confirmation" class="panel-success">


   <div class="panel-success">{{Session::get('global-confirmation')}}</div>

  </div>


 @endif







 @if(Session::has('global-success'))

 <div id="global-success" class="alert-box success ">

     <div class="panel-success">
         <strong>Success:</strong>{{Session::get('global-success')}}
     </div>
            <button class="close"><a class="close-reveal-modal">&#215;</a></button>
 </div>


    @endif




    @if(Session::has('global-warning'))
<div id="global-warning" class="alert alert-warning alert-dismissible" role="alert">
  <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
  <strong>Warning: </strong> {{ Session::get('global-warning')}}
</div>
@endif

@if(Session::has('global-info'))
<div id="global-info" class="alert alert-info alert-dismissible" role="alert">
  <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
  <strong>Please note: </strong> {{ Session::get('global-info')}}
</div>
@endif

@if(Session::has('global-danger'))

 <div id="global-danger" class="alert alert-danger " role="alert">

    <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
    <strong>Please note: </strong> {{ Session::get('global-danger')}}

 </div>

 @endif




@yield('content')



 @yield('scripts')


    </body>

<html>
