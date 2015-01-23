<!DocType html>

  <head>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      
    <!-- Latest compiled and minified CSS -->

        <script src="{{asset('js/vendor/modernizr.js')}}"></script>
        <script src="https://js.stripe.com/v2"></script>


        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
        <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">
         <link href="//code.ionicframework.com/ionicons/1.5.2/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="{{asset('css/foundation.min.css')}}">
     <link href="{{asset('css/layout.css')}}" rel="stylesheet" type="text/css" />
    
      
        <!-- Optional theme -->
<!--<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">-->


<link rel="stylesheet" href="{{asset('css/main.css')}}">
<link rel="stylesheet" href="{{asset('css/mystyles.css')}}">
<link rel="stylesheet" href="{{asset('css/tips.css')}}">
<link rel="stylesheet" href="{{asset('css/intlTelInput.css')}}">

<noscript>
            <link rel="stylesheet" href="css/skel.css" />
            <link rel="stylesheet" href="css/style.css" />
            <link rel="stylesheet" href="css/style-xlarge.css" />
      
        </noscript>




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




        <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
        <script src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>

 <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
  <script src="{{asset('js/script.js')}}"></script>
  
        {{--<script src="{{asset('js/recap.js')}}"></script>--}} 
         <script src="{{asset('js/jv.js')}}"></script>

         
   
         <script src="{{asset('js/vendor/jquery.cookie.js')}}"></script> <!-- Optional -->
         <script src="{{asset('js/alerts.js')}}"></script>
         <script src="{{asset('js/custom.js')}}"></script>
      

                
                <script>
                  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

                  ga('create', 'UA-56093034-1', 'auto');
                  ga('send', 'pageview');

                </script>

        <script>

         var confirmation = $('#confirmation');
         if(confirmation){
         confirmation.append('body');


            }
         </script>
         <script type="text/javascript"><!--
document.write(unescape("%3Cscript id='pap_x2s6df8d' src='" + (("https:" == document.location.protocol) ? "https://" : "http://") + 
"dialer.dial4dough.com/scripts/trackjs.js' type='text/javascript'%3E%3C/script%3E"));//-->
</script>
<script type="text/javascript"><!--
PostAffTracker.setAccountId('default1');
try {
PostAffTracker.track();
} catch (err) { }
//-->
</script>
        <script type="text/javascript">
(function(d, src, c) { var t=d.scripts[d.scripts.length - 1],s=d.createElement('script');s.id='la_x2s6df8d';s.async=true;s.src=src;s.onload=s.onreadystatechange=function(){var rs=this.readyState;if(rs&&(rs!='complete')&&(rs!='loaded')){return;}c(this);};t.parentElement.insertBefore(s,t.nextSibling);})(document,
'//dialer.dial4dough.ladesk.com/scripts/track.js',
function(e){  });
</script>


<script type="text/javascript" id="la_x2s6df8d" src="//dialer.dial4dough.ladesk.com/scripts/track.js"></script>
                <img src="//dialer.dial4dough.ladesk.com/scripts/pix.gif" onLoad="LiveAgentTracker.createButton('button1', this);"/>

 @yield('scripts')


    </body>

<html>
