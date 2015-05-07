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


<script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.3&appId=296623837209769";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>



<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript">
    var sc_project=10428515;
    var sc_invisible=1;
    var sc_security="98419ed9";
    var scJsHost = (("https:" == document.location.protocol) ?
            "https://secure." : "http://www.");
    document.write("<sc"+"ript type='text/javascript' src='" +
    scJsHost+
    "statcounter.com/counter/counter.js'></"+"script>");
</script>
<noscript><div class="statcounter"><a title="web analytics"
                                      href="http://statcounter.com/" target="_blank"><img
                    class="statcounter"
                    src="http://c.statcounter.com/10428515/0/98419ed9/1/"
                    alt="web analytics"></a></div></noscript>
<!-- End of StatCounter Code for Default Guide -->

</body>
</html>