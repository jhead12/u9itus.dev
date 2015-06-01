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


    <noscript>
        <link rel="stylesheet" href="css/skel.css" />
        <link rel="stylesheet" href="css/style.css" />
        <link rel="stylesheet" href="css/style-wide.css" />
    </noscript>



	<!-- special IE-only canvas fix -->
	<!--[if IE]><script type="text/javascript" src="js/excanvas.js"></script><![endif]-->

</head>
<body>



<div id="fb-root"></div>



<!-- Header -->
<header id="header" class="skel-layers-fixed">
    <h1><a class="navbar-brand" href="{{URL::to('/')}}">Dial4dough</a></h1>




    <nav id="nav">
        <ul>
            <li><div class="fb-like" data-href="http://www.dial4dough.com" data-layout="standard" data-action="like" data-show-faces="true" data-share="true"></div></li>

            <li><a href="{{URL::to('/')}}">Home</a></li>

            <li>
                <a href="" class="icon fa-angle-down">Philosophy</a>
                <ul>
                    <li><a href="/polidream">Politicians Dream</a></li>

                    <li><a href="{{URL::to('about')}}">About</a></li>

                    <li><a class="fb-share-button" data-href="https://www.dial4dough.com" data-layout="button"></a></li>
                    {{--<li>--}}
                    {{--<a href="">Submenu</a>--}}
                    {{--<ul>--}}
                    {{--<li><a href="#">Option One</a></li>--}}
                    {{--<li><a href="#">Option Two</a></li>--}}
                    {{--<li><a href="#">Option Three</a></li>--}}
                    {{--<li><a href="#">Option Four</a></li>--}}
                    {{--</ul>--}}
                    {{--</li>--}}
                </ul>
            </li>
            <li><a href="https://dialer.dial4dough.com/affiliates/login.php">Member Login</a></li>
            <li><a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="button">Sign Up</a></li>
        </ul>
    </nav>
</header>
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
