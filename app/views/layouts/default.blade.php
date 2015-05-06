<!docType html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="google-site-verification" content="OzhSWDz72APDh5zTIpCvWki0bnXbTUh3SroT0_2UQ-s" />
	<title>Dial4dough</title>

<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
	{{--<link rel="stylesheet" href="{{asset('css/main.css')}}">--}}
    <link rel="stylesheet" href="{{asset('css/style.css')}}">

    <link rel="stylesheet" type="text/css" href="{{asset('css/flashblock.css')}}" />



	<!-- special IE-only canvas fix -->
	<!--[if IE]><script type="text/javascript" src="js/excanvas.js"></script><![endif]-->

</head>
<body class="landing">



<div id="fb-root"></div>


	@include('layouts.partials.nav')
	@include('layouts.partials.alerts')


	@yield('content')

	   



</body>
<script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.3&appId=296623837209769";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
</html>
