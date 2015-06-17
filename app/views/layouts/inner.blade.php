<!docType html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>Dial4dough</title>

<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
	<link rel="stylesheet" href="{{asset('css/main.css')}}">
	<link rel="stylesheet" type="text/css" href="{{asset('css/flashblock.css')}}" />

    @yield('style')

	<!-- special IE-only canvas fix -->
	<!--[if IE]><script type="text/javascript" src="js/excanvas.js"></script><![endif]-->

</head>
<body>
	@include('layouts.partials.innerNav')
	


	@yield('content')

	   



</body>

<script src="{{asset('js/jquery.min.js')}}"></script>

<script type="text/javascript">
    document.write(unescape("%3Cscript id=%27pap_x2s6df8d%27 src=%27" + (("https:" == document.location.protocol) ? "https://" : "http://") + "dialer.dial4dough.com/scripts/trackjs.js%27 type=%27text/javascript%27%3E%3C/script%3E"));
</script>
<script type="text/javascript">PostAffTracker.setAccountId('default1');

    PostAffTracker.writeCookieToCustomField('pap_ab78y5t4a', '', 'pap_custom');
</script>


<script src="{{asset('js/obj.js')}}">


</script>

{{--<script type="text/javascript">--}}
    {{--$('li.unit').hover(--}}

            {{--function(){--}}

                {{--$(this).delay( 1800 ).addClass('active')--}}
            {{--},--}}
            {{--function(){--}}
                {{--$(this).delay( 1800 ).removeClass('active');--}}

            {{--});--}}
{{--</script>--}}

</html>
